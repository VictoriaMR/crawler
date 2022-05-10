<?php
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Sunra\PhpSimple\HtmlDomParser;
use Illuminate\Database\Capsule\Manager as Capsule;
use Overtrue\Pinyin\Pinyin;

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
        $this->url = 'https://www.51ape.com';

        //实例化爬取线程
    	$this->oClient = new Client([
            'timeout' => 30,
        ]);
    }

    protected function zhuanji()
    {
        echo __FUNCTION__.' start !!!'.PHP_EOL;
        $total = 48;
        for ($i=1; $i <= $total; $i++) { 
            $sFile = sprintf('%s/%s/page%d.html', $this->aConfig['storage'], __FUNCTION__, $i);
            if (!is_file($sFile)) {
                try
                {
                    $url = $i == 1 ? 'https://www.51ape.com/zhuanji/' : 'https://www.51ape.com/zhuanji/index_'.$i.'.html';
                    $oGuzzle = $this->oClient->get($url);
                    $this->oCookie = new \GuzzleHttp\Cookie\CookieJar();
                    if (200 == $oGuzzle->getStatusCode())
                    {
                        $sGuzzle = $oGuzzle->getBody()->getContents();
                        existsOrCreate($sFile);
                        file_put_contents($sFile, $sGuzzle);
                        echo $sFile.' downloaded !!'.PHP_EOL;
                    }else
                    {
                        echo 'file download fail!!'.PHP_EOL;
                    } 
                } catch (\GuzzleHttp\Exception\ConnectException $e) {
                    echo 'connection error'.PHP_EOL;
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    echo 'guzzle error'.PHP_EOL;
                }
            }
        }
        $insertData = [];
        Capsule::table('album')->truncate();
        for ($i=1; $i <= $total; $i++) { 
            $sFile = sprintf('%s/%s/page%d.html', $this->aConfig['storage'], __FUNCTION__, $i);
            echo $sFile.PHP_EOL;
            if (is_file($sFile) && $oHtml = HtmlDomParser::str_get_html(file_get_contents($sFile))) {
                foreach ($oHtml->find('.news', 0)->find('.blk_nav') as $value) {
                    $href = $value->find('a', 0)->href;
                    $name = $value->find('a', 0)->plaintext;
                    $singerName = trim(rtrim(trim(mb_substr($name, 0, mb_strpos($name, '专辑《'))), '-'));
                    $sLen = mb_strpos($name, '《');
                    $name = trim(mb_substr($name, $sLen+1, mb_strpos($name, '》')-$sLen-1));
                    $insertData = [
                        'singer_name' => $singerName,
                        'name' => $name,
                        'href' => $href,
                        'source_name' => trim($value->find('a', 0)->plaintext),
                    ];
                    Capsule::table('album')->insert($insertData);
                }
            }
        }
        dd('end');
    }

    public function zhuanjiList()
    {
        echo __FUNCTION__.' start !!!'.PHP_EOL;
        $list = Capsule::table('album')->whereNotIn('album_id', [667, 702, 826, 922, 935])->get();
        foreach ($list as $value) {
            $sFile = sprintf('%s/%s/%d.html', $this->aConfig['storage'], __FUNCTION__, $value->album_id);
            if (!is_file($sFile)) {
                try
                {
                    $url = $value->href;
                    $oGuzzle = $this->oClient->get($url);
                    $this->oCookie = new \GuzzleHttp\Cookie\CookieJar();
                    if (200 == $oGuzzle->getStatusCode())
                    {
                        $sGuzzle = $oGuzzle->getBody()->getContents();
                        existsOrCreate($sFile);
                        file_put_contents($sFile, $sGuzzle);
                        echo $sFile.' downloaded !!'.PHP_EOL;
                    }else
                    {
                        echo $sFile.' file download fail!!'.PHP_EOL;
                    } 
                } catch (\GuzzleHttp\Exception\ConnectException $e) {
                    echo $sFile.' connection error'.PHP_EOL;
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    echo $sFile.' guzzle error'.PHP_EOL;
                }
            }
        }
        Capsule::table('album_song')->truncate();
        foreach ($list as $value) {
            $sFile = sprintf('%s/%s/%d.html', $this->aConfig['storage'], __FUNCTION__, $value->album_id);
            if (is_file($sFile) && $oHtml = HtmlDomParser::str_get_html(file_get_contents($sFile))) {
                $obj = $oHtml->find('.a_none', 0);
                $url = trim($obj->href);
                
                $pwd = $obj ? trim(str_replace(['提取', '密码：', '无'], '', $obj->next_sibling()->plaintext)) : '';
                if (!empty($pwd)) {
                    Capsule::table('album')->where('album_id', $value->album_id)->update(['url'=>$url,'pwd'=>$pwd]);
                    $insertData = [];
                    $start = false;
                    foreach ($oHtml->find('#newstext_2 p') as $key=>$songV) {
                        if (!$start && (strpos($songV->plaintext, '曲目') !== false || strpos($songV->plaintext, '专辑') !== false || strpos($songV->plaintext, '歌曲') !== false || stripos($songV->plaintext, 'disc') !== false || stripos($songV->plaintext, 'disk') !== false || $songV->plaintext == '北上列车 EP' || $songV->plaintext == 'CD1' || stripos($songV->plaintext, '01') !== false || $songV->plaintext == 'ACD')) {
                            $start = true;
                            $insertData = [];
                            continue;
                        }
                        if (!$start) {
                            $insertData = [];
                            continue;
                        }
                        if ($songV->plaintext == '&nbsp;' || stripos($songV->plaintext, 'disc') !== false || stripos($songV->plaintext, 'disk') !== false || $songV->plaintext == '南下专线 EP' || $songV->plaintext == 'CD2' || $songV->plaintext == 'BCD') {
                            continue;
                        }
                        $sourceName = trim($songV->plaintext);
                        $insertData[] = [
                            'album_id' => $value->album_id,
                            'name' => preg_replace('/^\d+(\.)?(\s)?(、)?/', '', $sourceName),
                            'source_name' => $sourceName,
                        ];
                    }
                    if (empty($insertData)) {
                        echo $sFile.PHP_EOL;
                        // dd();
                    } else {
                        Capsule::table('album_song')->insert($insertData);

                    }
                }
            }
        }
        dd('end');

    }

    protected function artList()
    {
        echo __FUNCTION__.' start !!!'.PHP_EOL;
        $sFile = sprintf('%s/%s.html', $this->aConfig['storage'], __FUNCTION__);
        for ($sRun = 0; $sRun < 9; $sRun ++)
        { 
            if (!is_file($sFile))
            {
                try
                {
                    $oGuzzle = $this->oClient->get($this->url.'/artist/');
                    $this->oCookie = new \GuzzleHttp\Cookie\CookieJar();
                    if (200 == $oGuzzle->getStatusCode())
                    {
                        $sGuzzle = $oGuzzle->getBody()->getContents();
                        existsOrCreate($sFile);
                        file_put_contents($sFile, $sGuzzle);
                        echo $sFile.' downloaded !!'.PHP_EOL;
                    }else
                    {
                        echo 'file download fail!!'.PHP_EOL;
                    } 
                } catch (\GuzzleHttp\Exception\ConnectException $e) {
                    echo 'connection error'.PHP_EOL;
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    echo 'guzzle error'.PHP_EOL;
                } 
            }
        }
        $insertData = [];
        //解析HTML文件
        if (is_file($sFile) && $oHtml = HtmlDomParser::str_get_html(file_get_contents($sFile)))
        {
            $list = $oHtml->find('.over .n_one');
            foreach ($list as $key=>$value) {
                $group = trim($value->find('.blue', 0)->plaintext);
                $singerList = $value->find('.gs_a');
                foreach ($singerList as $sk=>$sv) {
                    $href = trim($sv->find('a', 0)->href);
                    if ($href == 'javascript:void(0)') {
                        $name = $sv->find('a', 0)->plaintext;
                        if ($sv->find('a span')) {
                            $name = str_replace($sv->find('a span', 0)->plaintext, '', $name);
                        }
                        $name = trim($name);
                        $insertData[$name] = [
                            'name' => $name,
                            'group' => $group,
                            'href' => $href,
                        ];
                        // continue;
                    }
                }
            }
        }
        if (!empty($insertData)) {
            // Capsule::table('singer')->truncate();
            Capsule::table('singer')->insert($insertData);
        }
        return true;
    }

    protected function songList()
    {
        $list = Capsule::table('singer')->get();
        foreach ($list as $key=>$value) {
            $sFile = sprintf('%s/%s/%d-1.html', $this->aConfig['storage'], __FUNCTION__, $value->singer_id);
            if (!is_file($sFile))
            {
                $url = $value->href;
                try
                {
                    $oGuzzle = $this->oClient->get($url);
                    $this->oCookie = new \GuzzleHttp\Cookie\CookieJar();
                    if (200 == $oGuzzle->getStatusCode())
                    {
                        $sGuzzle = $oGuzzle->getBody()->getContents();
                        existsOrCreate($sFile);
                        file_put_contents($sFile, $sGuzzle);
                        echo $sFile.' downloaded !!'.PHP_EOL;
                    }else
                    {
                        echo $sFile.' file download fail!!'.PHP_EOL;
                    } 
                } catch (\GuzzleHttp\Exception\ConnectException $e) {
                    echo $url.' connection error'.PHP_EOL;
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    echo $url.' guzzle error'.PHP_EOL;
                } 
            }
        }
        foreach ($list as $key=>$value) {
            $sFile = sprintf('%s/%s/%d-1.html', $this->aConfig['storage'], __FUNCTION__, $value->singer_id);
            if (is_file($sFile) && $oHtml = HtmlDomParser::str_get_html(file_get_contents($sFile)))
            {
                $totalPage = $oHtml->find('.listpage a');
                if (!$totalPage) {
                    continue;
                }
                $totalPage = end($totalPage);
                $href = $totalPage->href;
                $totalPage = str_replace('.html', '', substr($href, strrpos($href, '_')+1));
                for ($i=2; $i<=$totalPage; $i++) {
                    $sFile = sprintf('%s/%s/%d-%d.html', $this->aConfig['storage'], __FUNCTION__, $value->singer_id, $i);
                    if (!is_file($sFile))
                    {
                        $url = $this->url.str_replace('_'.$totalPage, '_'.$i, $href);
                        try
                        {
                            $oGuzzle = $this->oClient->get($url);
                            $this->oCookie = new \GuzzleHttp\Cookie\CookieJar();
                            if (200 == $oGuzzle->getStatusCode())
                            {
                                $sGuzzle = $oGuzzle->getBody()->getContents();
                                existsOrCreate($sFile);
                                file_put_contents($sFile, $sGuzzle);
                                echo $sFile.' downloaded !!'.PHP_EOL;
                            }else
                            {
                                echo $sFile.' file download fail!!'.PHP_EOL;
                            } 
                        } catch (\GuzzleHttp\Exception\ConnectException $e) {
                            echo $url.' connection error'.PHP_EOL;
                        } catch (\GuzzleHttp\Exception\ClientException $e) {
                            dd( $href, $totalPage, $url);
                            echo $url.' guzzle error'.PHP_EOL;
                        } 
                    }
                }
            }
        }
        //解析
        $insertData = [];
        foreach ($list as $key=>$value) {
            $sFile = sprintf('%s/%s/%d-1.html', $this->aConfig['storage'], __FUNCTION__, $value->singer_id);
            if (is_file($sFile) && $oHtml = HtmlDomParser::str_get_html(file_get_contents($sFile)))
            {
                $tempArr = $this->getSongList($oHtml);
                foreach ($tempArr as $sv) {
                    $sv['singer_id'] = $value->singer_id;
                    $insertData[] = $sv;
                }
                $totalPage = $oHtml->find('.listpage a');
                if (!$totalPage) {
                    continue;
                }
                $totalPage = end($totalPage);
                $href = $totalPage->href;
                $totalPage = str_replace('.html', '', substr($href, strrpos($href, '_')+1));
                for ($i=2; $i<=$totalPage; $i++) {
                    $sFile = sprintf('%s/%s/%d-%d.html', $this->aConfig['storage'], __FUNCTION__, $value->singer_id, $i);
                    if (is_file($sFile) && $oHtml = HtmlDomParser::str_get_html(file_get_contents($sFile)))
                    {
                        $tempArr = $this->getSongList($oHtml);
                        foreach ($tempArr as $sv) {
                            $sv['singer_id'] = $value->singer_id;
                            $insertData[] = $sv;
                        }
                    }
                }
            }
        }
        if (!empty($insertData)) {
            Capsule::table('song')->truncate();
            $insertData = array_chunk($insertData, 4000);
            foreach ($insertData as $value) {
                Capsule::table('song')->insert($value);
            }
        }
        return true;
    }

    protected function getSongList($oHtml) {
        $insertData = [];
        $songList = $oHtml->find('.news', 0)->find('.blk_nav');
        foreach ($songList as $sk=>$sv) {
            $href = trim($sv->find('a', 0)->href);
            $name = trim($sv->find('a', 0)->plaintext);
            $tempArr = explode('.', $name);
            $type = array_pop($tempArr);
            $name = implode('.', $tempArr);
            $insertData[] = [
                'name' => $name,
                'type' => $type,
                'href' => $href,
            ];
        }
        return $insertData;
    }

    protected function initSong()
    {
        echo __FUNCTION__.' start !!!'.PHP_EOL;
        $songList = Capsule::table('song')->where('status', '0')->get();
        foreach ($songList as $sk=>$sv) {
            $sFile = sprintf('%s/%s/%d.html', $this->aConfig['storage'], __FUNCTION__, $sv->song_id);
            if (!is_file($sFile)) {
                $url = $sv->href;
                try
                {
                    $oGuzzle = $this->oClient->get($url);
                    $this->oCookie = new \GuzzleHttp\Cookie\CookieJar();
                    if (200 == $oGuzzle->getStatusCode())
                    {
                        $sGuzzle = $oGuzzle->getBody()->getContents();
                        existsOrCreate($sFile);
                        file_put_contents($sFile, $sGuzzle);
                        echo $sFile.' downloaded !!'.PHP_EOL;
                    } else {
                        echo $url.' file download fail!!'.PHP_EOL;
                    } 
                } catch (\GuzzleHttp\Exception\ConnectException $e) {
                    echo $url.' connection error'.PHP_EOL;
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    echo $url.' guzzle error'.PHP_EOL;
                }       
            }
        }
        foreach ($songList as $sk=>$sv) {
            $sFile = sprintf('%s/%s/%d.html', $this->aConfig['storage'], __FUNCTION__, $sv->song_id);
            if (is_file($sFile) && $oHtml = HtmlDomParser::str_get_html(file_get_contents($sFile))) {
                $obj = $oHtml->find('.a_none', 0);
                $url = trim($obj->href);
                echo $sFile.PHP_EOL;
                $pwd = $obj ? trim(str_replace(['提取', '密码：', '无'], '', $obj->next_sibling()->plaintext)) : '';
                if (empty($pwd)) {
                    Capsule::table('song')->where('song_id', $sv->song_id)->update(['status'=>'2']);
                } else {
                    Capsule::table('song')->where('song_id', $sv->song_id)->update(['url'=>$url, 'pwd'=>$pwd, 'status'=>1]);
                }
            }
        }
        return true;
    }

    protected function category()
    {
        echo __FUNCTION__.' start !!!'.PHP_EOL;
        $sFile = sprintf('%s/%s.html', $this->aConfig['storage'], __FUNCTION__);
        if (!is_file($sFile))
        {
            try
            {
                $oGuzzle = $this->oClient->get($this->url.'/tags/');
                $this->oCookie = new \GuzzleHttp\Cookie\CookieJar();
                if (200 == $oGuzzle->getStatusCode())
                {
                    $sGuzzle = $oGuzzle->getBody()->getContents();
                    existsOrCreate($sFile);
                    file_put_contents($sFile, $sGuzzle);
                    echo $sFile.' downloaded !!'.PHP_EOL;
                }else
                {
                    echo 'file download fail!!'.PHP_EOL;
                } 
            } catch (\GuzzleHttp\Exception\ConnectException $e) {
                echo 'connection error'.PHP_EOL;
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                echo 'guzzle error'.PHP_EOL;
            } 
        }
        if (is_file($sFile) && $oHtml = HtmlDomParser::str_get_html(file_get_contents($sFile)))
        {
            $groupArr =  Capsule::table('group')->get();
            $groupArr = array_object($groupArr);
            $groupArr = array_column($groupArr, 'group_id', 'name');
            $list = $oHtml->find('.n_one');
            foreach ($list as $key=>$value) {
                $group = trim($value->find('.blue', 0)->plaintext);
                $singerList = $value->find('.gs_a');
                foreach ($singerList as $sk=>$sv) {
                    $name = trim($sv->find('a', 0)->plaintext);
                    $insertData[] = [
                        'name' => $name,
                        'group_id' => $groupArr[$group] ?? 0,
                    ];
                }
            }
        }
        if (!empty($insertData)) {
            Capsule::table('category')->truncate();
            Capsule::table('category')->insert($insertData);
        }
        return true;
    }

    public function singerInfo()
    {
        set_error_handler (function($errno, $errstr, $errfile, $errline){
            echo $errno.PHP_EOL;
            echo $errstr.PHP_EOL;
            echo $errfile.PHP_EOL;
            echo $errline;
            exit();
        });
        echo __FUNCTION__.' start !!!'.PHP_EOL;
        $where = [];
        $singerList = Capsule::table('singer')->where('singer_id', '>', '312')->whereNotIn('singer_id', [257])->get();
        foreach ($singerList as $key=>$value) {
            $sFile = sprintf('%s/%s/%d.html', $this->aConfig['storage'], __FUNCTION__, $value->singer_id);
            if (!is_file($sFile)) {
                $url = 'https://baike.baidu.com/item/'.$value->name;
                try
                {
                    $oGuzzle = $this->oClient->get($url);
                    $this->oCookie = new \GuzzleHttp\Cookie\CookieJar();
                    if (200 == $oGuzzle->getStatusCode())
                    {
                        $sGuzzle = $oGuzzle->getBody()->getContents();
                        existsOrCreate($sFile);
                        file_put_contents($sFile, $sGuzzle);
                        echo $sFile.' downloaded !!'.PHP_EOL;
                    } else {
                        echo $url.' file download fail!!'.PHP_EOL;
                    } 
                } catch (\GuzzleHttp\Exception\ConnectException $e) {
                    echo $url.' connection error'.PHP_EOL;
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    echo $url.' guzzle error'.PHP_EOL;
                }       
            }
        }
        
        $pinyin = new Pinyin();
        try {
            // Capsule::table('singer_data')->truncate();
            // Capsule::table('album')->truncate();
            foreach ($singerList as $key=>$value) {
                $insertData = [];
                $sFile = sprintf('%s/%s/%d.html', $this->aConfig['storage'], __FUNCTION__, $value->singer_id);
                if (filesize($sFile) < 10240) continue;
                $content = file_get_contents($sFile);
                $content = substr($content, 0, strrpos($content, '</body>')+7).'</html>';
                if (is_file($sFile) && $oHtml = HtmlDomParser::str_get_html($content)) {
                    echo $sFile.PHP_EOL;
                    $baseInfo = $oHtml->find('.lemma-summary', 0)->plaintext;
                    $baseInfo = str_replace(['&nbsp;'], '', preg_replace('/\[\d+(-)?(\d+)?\]/', '', $baseInfo));
                    $baseInfo = implode('。', array_map('trim', explode('。', $baseInfo)));
                    $baseInfo = str_replace('  ', '', $baseInfo);
                    $desc = preg_replace('/\d+年\d+月\d+日(，)?|\d+年\d+月(，)?|\d+年(，)?|\d+月\d+日(，)?|同年，/', '', $baseInfo);

                    $keyword =  $oHtml->find('[name="keywords"]', 0)->getAttribute('content');
                    if (empty($keyword)) {
                        $keyword = $value->name.'，'.$value->name.'歌曲，'.$value->name.'专辑，'.$value->name.'信息，'.$value->name.'经历，'.$value->name.'主要作品，'.$value->name.'获奖记录';
                    } else {
                        $tempArr = [];
                        foreach (explode(',', $keyword) as $kk=>$kv) {
                            $kv = trim($kv);
                            if ($kk == 0) {
                                $name = $kv;
                            }
                            if ($kk == 1) {
                               $tempArr[] = $name.'歌曲';
                               $tempArr[] = $name.'专辑';
                            }
                            $tempArr[] = $kv;
                        }
                        $keyword = implode('，', $tempArr);
                        $keyword = str_replace($name, $value->name, $keyword);
                    }
                    $data = [];
                    if (empty($value->name_en)) {
                        $name_en = explode('，', $baseInfo)[0];
                        if (strpos($name_en, '（') !== false) {
                            $data['name_en'] = trim(str_replace('）', '', explode('（', $name_en)[1]));
                        }
                    }
                    $data['desc'] = trim($desc);
                    $data['keyword'] = trim($keyword);
                    Capsule::table('singer')->where('singer_id', $value->singer_id)->update($data);
                    $baseInfo = str_replace('。', PHP_EOL, $baseInfo);
                    $insertData['基本信息'] = ['singer_id'=>$value->singer_id, 'name'=>'基本信息', 'value'=>$baseInfo];
                    //其他信息
                    $list = $oHtml->find('.basicInfo-block .name');
                    foreach ($list as $lk=>$lv) {
                        $name = trim(str_replace(['&nbsp;', '  '], ['', ' '], trim($lv->plaintext)));
                        $nameValue = trim($oHtml->find('.basicInfo-block .value', $lk)->plaintext);
                        $nameValue = trim(str_replace(['&nbsp;', '  '], ['', ' '], preg_replace('/\[\d+(-)?(\d+)?\]/', '', $nameValue)));
                        $insertData[$name] = ['singer_id'=>$value->singer_id, 'name'=>$name, 'value'=>$nameValue];
                    }
                    if (!empty($insertData)) {
                        Capsule::table('singer_data')->insert($insertData);
                    }
                    $insertData = [];
                    //专辑
                    $obj = $oHtml->find('.module-musicAlbum .album-item');
                    foreach ($obj as $ak=>$av) {
                        if ($av->find('.albumName')) {
                            $name = trim($av->find('.albumName', 0)->plaintext);
                            $date = $av->find('.albumDate', 0)->plaintext;
                            $avater = trim($av->find('.cover', 0)->src);
                        } else {
                            $name = trim($av->find('.title', 0)->plaintext);
                            $date = $av->find('.align-center', 0)->plaintext;
                            $avater = '';
                        }
                        $insertData[] = [
                            'singer_id'=>$value->singer_id,
                            'name' => $name,
                            'avatar' => $avater,
                            'name_en' => str_replace('_', ' ',trim($pinyin->permalink($name, '_'))),
                            'release_time' => date('Y-m-d', strtotime(trim(str_replace(['发行时间', '&nbsp;', '  '], ['', '', ' '], preg_replace('/\[\d+(-)?(\d+)?\]/', '', $date))))),
                        ];
                    }
                    if (!empty($insertData)) {
                        Capsule::table('album')->insert($insertData);
                    }
                }
            }
        } catch (\Throwable $e) {
            dd($e->getMessage());
        }
        dd('end');
        return true;
    }
}