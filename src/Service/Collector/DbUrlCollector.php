<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service\Collector;

use BaserCore\Service\ContentsServiceInterface;
use BaserCore\Utility\BcContainerTrait;
use Cake\Core\Plugin;
use Cake\ORM\TableRegistry;

/**
 * DbUrlCollector
 *
 * コンテンツ管理 DB からサイト内の全公開 URL を列挙する。
 * 生成する URL はすべてフル URL（コンテンツ管理上の URL を
 * ContentsService::getUrl() で変換したもの）。
 */
class DbUrlCollector
{

    use BcContainerTrait;

    /**
     * DB 由来の URL を列挙する
     *
     * 1件を ['url', 'content_id', 'content_kind', 'content_title', 'content_status'] の
     * 配列として yield する。大量データに備えチャンク走査する。
     *
     * @param int|null $siteId 対象サイト。null で全サイト
     * @param array $options include_blog_extra_urls 等
     * @param callable|null $logger 進捗通知
     * @return \Generator
     */
    public function collect(?int $siteId = null, array $options = [], ?callable $logger = null): \Generator
    {
        set_time_limit(0);
        /** @var ContentsServiceInterface $contentsService */
        $contentsService = $this->getService(ContentsServiceInterface::class);
        $descriptions = $this->fetchSeoDescriptions();

        $page = 1;
        $limit = 500;
        while(true) {
            $params = ['status' => 'publish'];
            if ($siteId) $params['site_id'] = $siteId;
            $contents = $contentsService->getIndex($params)
                ->orderBy(['Contents.site_id' => 'ASC', 'Contents.lft' => 'ASC'])
                ->limit($limit)
                ->page($page)
                ->all();
            if (!$contents->count()) break;

            foreach($contents as $content) {
                try {
                    $url = $contentsService->getUrl($content->url, true, (bool)($content->site->use_subdomain ?? false));
                } catch (\Throwable $e) {
                    if ($logger) $logger(__d('baser_core', 'URL生成に失敗しました。content_id: {0} {1}', $content->id, $e->getMessage()));
                    continue;
                }
                yield [
                    'url' => $url,
                    'content_id' => $content->id,
                    'content_kind' => $content->type,
                    'content_title' => $content->title,
                    'content_description' => $descriptions[(int)$content->id] ?? null,
                    'content_status' => true,
                ];

                if ($content->type === 'BlogContent' && Plugin::isLoaded('BcBlog')) {
                    yield from $this->collectBlogPosts($content, $url, $options, $logger);
                }
                if ($content->type === 'CustomContent' && Plugin::isLoaded('BcCustomContent')) {
                    yield from $this->collectCustomEntries($content, $logger);
                }
            }
            if ($contents->count() < $limit) break;
            $page++;
        }
    }

    /**
     * BcSeo の meta description を entity_id => description のマップで取得する
     *
     * contents.description は 5.2 で seo_metas（table_alias='Contents'）へ移行済み。
     * BcSeo が無効な環境では空配列を返す。
     *
     * @return array<int, string>
     */
    protected function fetchSeoDescriptions(): array
    {
        if (!Plugin::isLoaded('BcSeo')) return [];
        $descriptions = [];
        try {
            $rows = TableRegistry::getTableLocator()->get('BcSeo.SeoMetas')->find()
                ->where(['table_alias' => 'Contents', 'description IS NOT' => null, 'description !=' => ''])
                ->select(['entity_id', 'description'])
                ->disableHydration()
                ->all();
            foreach($rows as $row) {
                $descriptions[(int)$row['entity_id']] = (string)$row['description'];
            }
        } catch (\Throwable) {
            // seo_metas が引けない場合は description なしで続行する
        }
        return $descriptions;
    }

    /**
     * ブログ記事の URL を列挙する
     *
     * include_blog_extra_urls オプション有効時は、記事の走査と同時に
     * 日付・著者・カテゴリ別の件数を集計し、派生 URL（アーカイブ）も列挙する。
     *
     * @param \BaserCore\Model\Entity\Content $content BlogContent の Content 行
     * @param string $contentUrl ブログトップのフル URL（末尾スラッシュ付き）
     * @param array $options
     * @param callable|null $logger
     * @return \Generator
     */
    protected function collectBlogPosts($content, string $contentUrl, array $options = [], ?callable $logger = null): \Generator
    {
        /** @var \BcBlog\Service\BlogPostsService $blogPostsService */
        $blogPostsService = $this->getService(\BcBlog\Service\BlogPostsServiceInterface::class);
        $includeArchives = !empty($options['include_blog_extra_urls']);
        $agg = ['total' => 0, 'dates' => [], 'authors' => [], 'categories' => []];

        $page = 1;
        $limit = 500;
        while(true) {
            try {
                $posts = $blogPostsService->getIndex([
                    'blog_content_id' => $content->entity_id,
                    'status' => 'publish',
                    'limit' => $limit,
                    'page' => $page,
                    'direction' => 'ASC',
                    'sort' => 'no',
                ])->all();
            } catch (\Throwable $e) {
                if ($logger) $logger(__d('baser_core', 'ブログ記事の列挙に失敗しました。blog_content_id: {0} {1}', $content->entity_id, $e->getMessage()));
                return;
            }
            if (!$posts->count()) break;
            foreach($posts as $post) {
                try {
                    $url = $blogPostsService->getUrl($content, $post, true);
                } catch (\Throwable $e) {
                    if ($logger) $logger(__d('baser_core', 'ブログ記事のURL生成に失敗しました。id: {0} {1}', $post->id, $e->getMessage()));
                    continue;
                }
                yield [
                    'url' => $url,
                    'content_id' => $content->id,
                    'content_kind' => 'BlogPost',
                    'content_title' => $post->title,
                    'content_status' => true,
                ];
                if ($includeArchives) {
                    $agg['total']++;
                    if ($post->posted) {
                        $year = $post->posted->format('Y');
                        $month = $post->posted->format('Y/m');
                        $day = $post->posted->format('Y/m/d');
                        $agg['dates'][$year] = ($agg['dates'][$year] ?? 0) + 1;
                        $agg['dates'][$month] = ($agg['dates'][$month] ?? 0) + 1;
                        $agg['dates'][$day] = ($agg['dates'][$day] ?? 0) + 1;
                    }
                    if ($post->user_id) {
                        $agg['authors'][(int)$post->user_id] = ($agg['authors'][(int)$post->user_id] ?? 0) + 1;
                    }
                    if ($post->blog_category_id) {
                        $agg['categories'][(int)$post->blog_category_id] = ($agg['categories'][(int)$post->blog_category_id] ?? 0) + 1;
                    }
                }
            }
            if ($posts->count() < $limit) break;
            $page++;
        }

        if ($includeArchives && $agg['total']) {
            yield from $this->collectBlogArchives($content, $contentUrl, $agg, $logger);
        }
    }

    /**
     * ブログの派生 URL（一覧 RSS・ページ送り・カテゴリ・タグ・日付・著者）を列挙する
     *
     * @param \BaserCore\Model\Entity\Content $content BlogContent の Content 行
     * @param string $contentUrl ブログトップのフル URL（末尾スラッシュ付き）
     * @param array $agg collectBlogPosts() で集計した件数
     * @param callable|null $logger
     * @return \Generator
     */
    protected function collectBlogArchives($content, string $contentUrl, array $agg, ?callable $logger = null): \Generator
    {
        try {
            $blogContent = TableRegistry::getTableLocator()->get('BcBlog.BlogContents')->get($content->entity_id);
        } catch (\Throwable $e) {
            if ($logger) $logger(__d('baser_core', 'ブログ設定の取得に失敗しました。entity_id: {0} {1}', $content->entity_id, $e->getMessage()));
            return;
        }
        $listCount = max((int)($blogContent->list_count ?? 10), 1);

        // アーカイブ URL（title, url, ページ送り対象の件数）を組み立てる
        $archives = [];
        $archives[] = ['title' => 'RSS', 'url' => $contentUrl . 'index.rss', 'count' => 0];
        // 一覧のページ送り（1ページ目はブログトップ自身）
        $archives[] = ['title' => __d('baser_core', '一覧'), 'url' => $contentUrl, 'count' => $agg['total'], 'skipFirst' => true];

        foreach($this->rollupCategoryCounts((int)$content->entity_id, $agg['categories']) as $name => $category) {
            $archives[] = [
                'title' => __d('baser_core', 'カテゴリ: {0}', $category['title']),
                'url' => $contentUrl . 'archives/category/' . rawurlencode($name),
                'count' => $category['count'],
            ];
        }
        if (!empty($blogContent->tag_use)) {
            foreach($this->aggregateTagCounts((int)$content->entity_id) as $name => $count) {
                $archives[] = [
                    'title' => __d('baser_core', 'タグ: {0}', $name),
                    'url' => $contentUrl . 'archives/tag/' . rawurlencode($name),
                    'count' => $count,
                ];
            }
        }
        foreach($agg['dates'] as $dateKey => $count) {
            $archives[] = [
                'title' => __d('baser_core', '日付: {0}', $dateKey),
                'url' => $contentUrl . 'archives/date/' . $dateKey,
                'count' => $count,
            ];
        }
        foreach($agg['authors'] as $authorId => $count) {
            $archives[] = [
                'title' => __d('baser_core', '著者: {0}', $authorId),
                'url' => $contentUrl . 'archives/author/' . $authorId,
                'count' => $count,
            ];
        }

        foreach($archives as $archive) {
            if (empty($archive['skipFirst'])) {
                yield [
                    'url' => $archive['url'],
                    'content_id' => $content->id,
                    'content_kind' => 'BlogArchive',
                    'content_title' => $content->title . ' ' . $archive['title'],
                    'content_status' => true,
                ];
            }
            // 2ページ目以降のページ送り
            $pageMax = ($listCount >= 1)? (int)ceil($archive['count'] / $listCount) : 1;
            for($pageNum = 2; $pageNum <= $pageMax; $pageNum++) {
                yield [
                    'url' => $archive['url'] . ((str_contains($archive['url'], '?'))? '&' : '?') . 'page=' . $pageNum,
                    'content_id' => $content->id,
                    'content_kind' => 'BlogArchive',
                    'content_title' => $content->title . ' ' . $archive['title'] . ' (' . $pageNum . ')',
                    'content_status' => true,
                ];
            }
        }
    }

    /**
     * カテゴリ別件数を子孫カテゴリ込みでロールアップする
     *
     * フロントのカテゴリアーカイブは子孫カテゴリの記事も表示するため、
     * lft/rght を使って直下件数を親へ加算する。
     *
     * @param int $blogContentId
     * @param array<int, int> $directCounts blog_category_id => 直下件数
     * @return array<string, array{title: string, count: int}> カテゴリ name => 情報
     */
    protected function rollupCategoryCounts(int $blogContentId, array $directCounts): array
    {
        try {
            $categories = TableRegistry::getTableLocator()->get('BcBlog.BlogCategories')->find()
                ->select(['id', 'name', 'title', 'lft', 'rght'])
                ->where(['BlogCategories.blog_content_id' => $blogContentId])
                ->disableHydration()
                ->all()
                ->toList();
        } catch (\Throwable) {
            return [];
        }
        $result = [];
        foreach($categories as $category) {
            $count = $directCounts[(int)$category['id']] ?? 0;
            // 子孫カテゴリ（lft/rght が内側）の件数を加算
            foreach($categories as $target) {
                if ((int)$target['id'] === (int)$category['id']) continue;
                if ($target['lft'] <= $category['lft'] || $target['rght'] >= $category['rght']) continue;
                $count += $directCounts[(int)$target['id']] ?? 0;
            }
            if ($count > 0) {
                $result[(string)$category['name']] = [
                    'title' => (string)($category['title'] ?: $category['name']),
                    'count' => $count,
                ];
            }
        }
        return $result;
    }

    /**
     * タグ別件数を集計する（公開記事のみ）
     *
     * @param int $blogContentId
     * @return array<string, int> タグ name => 件数
     */
    protected function aggregateTagCounts(int $blogContentId): array
    {
        try {
            /** @var \BcBlog\Model\Table\BlogPostsTable $postsTable */
            $postsTable = TableRegistry::getTableLocator()->get('BcBlog.BlogPosts');
            $query = $postsTable->find();
            $rows = $query
                ->innerJoinWith('BlogTags')
                ->where(['BlogPosts.blog_content_id' => $blogContentId] + $postsTable->getConditionAllowPublish())
                ->select(['tag_name' => 'BlogTags.name', 'cnt' => $query->func()->count('*')])
                ->groupBy(['BlogTags.name'])
                ->disableHydration()
                ->all();
        } catch (\Throwable) {
            return [];
        }
        $counts = [];
        foreach($rows as $row) {
            $counts[(string)$row['tag_name']] = (int)$row['cnt'];
        }
        return $counts;
    }

    /**
     * カスタムエントリーの URL を列挙する
     *
     * @param \BaserCore\Model\Entity\Content $content CustomContent の Content 行
     * @param callable|null $logger
     * @return \Generator
     */
    protected function collectCustomEntries($content, ?callable $logger = null): \Generator
    {
        try {
            $customContent = TableRegistry::getTableLocator()
                ->get('BcCustomContent.CustomContents')
                ->get($content->entity_id);
            if (empty($customContent->custom_table_id)) return;
            /** @var \BcCustomContent\Service\CustomEntriesService $entriesService */
            $entriesService = $this->getService(\BcCustomContent\Service\CustomEntriesServiceInterface::class);
            $entriesService->setup((int)$customContent->custom_table_id);
        } catch (\Throwable $e) {
            if ($logger) $logger(__d('baser_core', 'カスタムコンテンツの解決に失敗しました。entity_id: {0} {1}', $content->entity_id, $e->getMessage()));
            return;
        }

        $page = 1;
        $limit = 500;
        while(true) {
            try {
                $entries = $entriesService->getIndex([
                    'status' => 'publish',
                    'limit' => $limit,
                    'page' => $page,
                ])->all();
            } catch (\Throwable $e) {
                if ($logger) $logger(__d('baser_core', 'カスタムエントリーの列挙に失敗しました。{0}', $e->getMessage()));
                return;
            }
            if (!$entries->count()) break;
            foreach($entries as $entry) {
                try {
                    $url = $entriesService->getUrl($content, $entry, true);
                } catch (\Throwable $e) {
                    if ($logger) $logger(__d('baser_core', 'カスタムエントリーのURL生成に失敗しました。id: {0} {1}', $entry->id, $e->getMessage()));
                    continue;
                }
                yield [
                    'url' => $url,
                    'content_id' => $content->id,
                    'content_kind' => 'CustomEntry',
                    'content_title' => $entry->title ?? $entry->name,
                    'content_status' => true,
                ];
            }
            if ($entries->count() < $limit) break;
            $page++;
        }
    }

}
