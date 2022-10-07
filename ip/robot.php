<?php
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Sunra\PhpSimple\HtmlDomParser;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * 爬虫
 */
class Robot extends Base
{
    private $aConfig;
    private $url;
    protected $oClient;

    public function __construct($aConfig)
    {
    	// setting
        $this->aConfig = $aConfig;
        $this->url = 'https://www.zdaye.com';

        //实例化爬取线程
    	$this->oClient = new Client([
            'timeout' => 30,
            'verify' => false,
            'base_uri' => $this->url,
        ]);
    }

    protected function getLink($content)
    {
        $dataArr = [];
        if ($oHtml = HtmlDomParser::str_get_html($content)) {
            $aArr = $oHtml->find('#J_posts_list .thread_item');
            foreach ($aArr as $aValue) {
                $href = $aValue->find('a', 0)->href;
                preg_match('/\d+/', $href, $match);
                if (!empty($match[0])) {
                    $dataArr[] = [
                        'number' => $match[0]
                    ];
                }
            }
        }
        return $dataArr;
    }

    public function sync()
    {
        echo __FUNCTION__.' start !!!'.PHP_EOL;
        $url = '/dayProxy/'.date('Y/n').'/1.html';
        $sGuzzle = $this->get($url);
        if (empty($sGuzzle)) {
           dd('数据初始化失败!!');
        }
        $this->echo('开始解析数据');
        //解析文本
        $insertArr = [];
        if ($oHtml = HtmlDomParser::str_get_html($sGuzzle)) {
            $insertArr = $this->getLink($sGuzzle);
            $aArr = $oHtml->find('#J_posts_list .page a');
            $aHrefArr = [];
            foreach ($aArr as $aValue) {
                $href = $aValue->href;
                if (strrpos($href, '.html')) {
                    $aHrefArr[] = $href;
                }
            }
            foreach ($aHrefArr as $aValue) {
                $sGuzzle = $this->get($aValue);
                if (!empty($sGuzzle)) {
                    $insertArr = array_merge($insertArr, $this->getLink($sGuzzle));
                }
            }
        }
        $max = Capsule::table('date_link')->max('number');
        $insertArr = array_reverse($insertArr);
        foreach ($insertArr as $aValue) {
            if ($aValue['number'] > $max) {
                Capsule::table('date_link')->insert($aValue);
            }
        }
        return true;
    }

    public function datelink()
    {
        $list = Capsule::table('date_link')->get();
        $list = array_object($list);
        foreach ($list as $aValue) {
            $url = sprintf('/dayProxy/ip/%d.html', $aValue['number']);
            $sFile = sprintf('%s/%s/%s.html', $this->aConfig['storage'], __FUNCTION__, $aValue['number']);
            $this->get($url, $sFile);
            sleep(rand(1, 5));
        }
        dd('end');
    }
}