<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use QL\QueryList;
use QL\Ext\AbsoluteUrl;

class CrawlInvestingCommoditiesNews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:investingCommonditiesNews {pages=2 : 采集的页码数}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '爬取英为财经期货新闻';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $pages = $this->argument('pages');
        $this->info('即将开始爬取英为财经期货新闻 ，爬取页数为' . $pages);
        $bar = $this->output->createProgressBar($pages);
        $articels = [];
        $bar->start();
        for ($i = 1; $i <= $pages; $i++) {
            $pageArticles = $this->performList($i);
            $articels = array_merge($articels, $pageArticles);
            $bar->advance();
        }
        $bar->finish();
        file_put_contents(base_path('public') .'/'. time() . '.json', json_encode($articels));
    }

    protected function performList($page)
    {
        $this->info('开始爬取第' . $page . '页面');

        $ql = QueryList::getInstance();
        $ql->use(AbsoluteUrl::class);
        $data = $ql->get('https://cn.investing.com/news/commodities-news/' . $page, null, [
            'headers' => [
                'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',
                'Accept-Encoding' => 'gzip, deflate, br',
            ]
        ])->rules([
            'title' => ['a.title', 'text'],
            'id'    => ["", "data-id"],
            'img'   => ['a.img img', 'data-src'],
            'url'   => ['a.title', 'href'],
        ])->range('.largeTitle article')->queryData(function ($item) use ($ql) {
            $item['url'] = $ql->absoluteUrlHelper('https://cn.investing.com/', $item['url']);
            return $item;
        });
        $total = count($data);
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        foreach ($data as $key => &$item) {
            $item = $this->performArticle($item, $page, $key + 1, $total);
            $bar->advance();
        }
        $bar->finish();
        $this->info('第' . $page . '页面爬取结束');
        return $data;
    }

    /**
     * @param $url string 文章详情地址
     * @param $page string 当前列表是第几页
     * @param $index int 当前文章在当前列表页面是第几个
     * @param $total int 当前列表页面文章总数
     */
    protected function performArticle($article, $page, $index, $total)
    {
        $this->info('开始抓取第' . $page . '页，第' . $index . '篇文章,本页面总计' . $total . '篇文章，本页还剩' . ($total - $index) . '篇未采集');
        $ql = QueryList::getInstance();
        $ql->use(AbsoluteUrl::class);
        dump($article['url']);
        $ql->get($article['url'], null, [
            'headers' => [
                'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',
                'Accept-Encoding' => 'gzip, deflate, br',
            ]
        ]);

        $time = $ql->find('div.contentSectionDetails span')->text();
        if (strpos($time, '以前') !== false) {
            $time = str_replace(['（', '）'], ['(', ''], $time);
            $time = str_replace([')', '日'], '', $time);
            $time = str_replace(['年', '月'], '-', $time);
            $time = explode('(', $time);
            $timeText = isset($time[1]) ? $time[1] : date('Y-m-d H:i');
        } else {
            $timeText = $time;
        }
        $time = strtotime($timeText);
//        $title= $ql->find('h1.articleHeader')->text();
        $content = $ql->find('div.WYSIWYG.articlePage');
        $content->find('div#imgCarousel,div.clear')->remove();
        $content = $content->html();

        $this->info('抓取第' . $page . '页，第' . $index . '篇文章爬取结束');
        $article['content'] = $content;
        $article['time'] = $time;
        $article['timeText'] = $timeText;
        return $article;
    }

}
