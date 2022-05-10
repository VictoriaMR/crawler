<?php
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Sunra\PhpSimple\HtmlDomParser;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * 爬虫
 */
class Robot
{
    private $aConfig;
    private $url;
    private $oClient;

    public function __construct($aConfig)
    {
    	// setting
        $this->aConfig = $aConfig;

        //实例化爬取线程
    	$this->oClient = new Client([
            'timeout' => 30,
        ]);
    }

    public function sync()
    {
        echo __FUNCTION__.' start !!!'.PHP_EOL;
        $i = 1;
        $list = Capsule::table('supplier_image')->get()->toArray();
        foreach ($list as $value) {
            $url = parse_url($value->url);
            parse_str($url['query'], $param);
            $name = $url['path'];
            $url = $url['scheme'].'://'.$url['host'].$url['path'];
            if (!empty($param)) {
                unset($param['name']);
                $url .= '?'.http_build_query($param);
            }
            $type = $param['format'] ?? 'jpg';
            $sFile = sprintf('%s%s.%s', $this->aConfig['storage'], $name, $type);
            $oGuzzle = $this->oClient->get($url);
            if (200 == $oGuzzle->getStatusCode()) {
                $sGuzzle = $oGuzzle->getBody()->getContents();
                if (!empty($sGuzzle)) {
                    existsOrCreate($sFile);
                    file_put_contents($sFile, $sGuzzle);
                    echo $sFile.' downloaded !!'.PHP_EOL;
                    Capsule::table('supplier_image')->where('supp_id',$value->supp_id)->update(['status'=>1]);
                }
            } else {
                echo 'network fail!!'.PHP_EOL;
            }
        }
        return false;
    }
}