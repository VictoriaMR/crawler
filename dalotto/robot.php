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
        $this->url = 'https://www.js-lottery.com/PlayZone/ajaxLottoData';
        $this->url = 'https://webapi.sporttery.cn/gateway/lottery/getHistoryPageListV1.qry?gameNo=85&provinceId=0&pageSize=50&pageNo=%s';

        //实例化爬取线程
    	$this->oClient = new Client([
            'timeout' => 30,
        ]);
    }

    public function sync()
    {
        echo __FUNCTION__.' start !!!'.PHP_EOL;
        $i = 1;
        $lastNo = Capsule::table('lottery')->max('qishu');
        $check = false;
        $keyName = ['num1', 'num2', 'num3', 'num4', 'num5', 'num6', 'num7'];
        $insert = [];
        $page = 0;
        while (!$check) {
            try {
                $page ++;
                $oGuzzle = $this->oClient->get(sprintf($this->url, $page));
                if (200 == $oGuzzle->getStatusCode()) {
                    $sGuzzle = $oGuzzle->getBody()->getContents();
                    if (!empty($sGuzzle)) {
                        $sGuzzle = json_decode($sGuzzle, true);
                        $list = $sGuzzle['value']['list'];
                        if (empty($list) && $sGuzzle['success']) {
                            $check = true;
                            break;
                        }
                        foreach ($list as $key => $value) {
                            $qishu = $value['lotteryDrawNum'];
                            if ($qishu <= $lastNo) {
                                $check = true;
                                break;
                            }
                            $arr = explode(' ', $value['lotteryDrawResult']);
                            $data = [
                                'num1' => $arr[0],
                                'num2' => $arr[1],
                                'num3' => $arr[2],
                                'num4' => $arr[3],
                                'num5' => $arr[4],
                                'num6' => $arr[5],
                                'num7' => $arr[6],
                            ];
                            $data['date'] = $value['lotteryDrawTime'];
                            $data['qishu'] = $qishu;
                            $insert[$qishu] = $data;
                        }
                    }
                } else {
                    echo 'network fail!!'.PHP_EOL;
                }
                $i ++;
            } catch (\GuzzleHttp\Exception\ConnectException $e) {
                echo 'connection error'.PHP_EOL;
            }
        }
        if (!empty($insert)) {
            ksort($insert);
            $insert = array_chunk($insert, 2000);
            foreach ($insert as $key => $value) {
                Capsule::table('lottery')->insert($value);
            }
        }
        return false;
    }

    public function getNumber()
    {
        echo __FUNCTION__.' start !!!'.PHP_EOL;
        $keyName = ['num1', 'num2', 'num3', 'num4', 'num5', 'num6', 'num7'];
        $data = Capsule::table('lottery')->select($keyName)->get()->toArray();
        $data1 = array_count_values(array_column($data, 'num1'));
        $data2 = array_count_values(array_column($data, 'num2'));
        $data3 = array_count_values(array_column($data, 'num3'));
        $data4 = array_count_values(array_column($data, 'num4'));
        $data5 = array_count_values(array_column($data, 'num5'));
        $data6 = array_count_values(array_column($data, 'num6'));
        $data7 = array_count_values(array_column($data, 'num7'));
        arsort($data1);
        arsort($data2);
        arsort($data3);
        arsort($data4);
        arsort($data5);
        arsort($data6);
        arsort($data7);
        print_r($data1);
        print_r($data2);
        print_r($data3);
        print_r($data4);
        print_r($data5);
        print_r($data6);
        print_r($data7);
        dd();
        $rate = (sqrt(5) - 1) / 2;
        // $rate = 0.8;
        $result = [];
        $data = [
            $data1,
            $data2,
            $data3,
            $data4,
            $data5,
        ];
        $inM = [4, 6, 8, 14, 16];
        foreach ($data as $key => $value) {
            $count = 1;
            foreach ($value as $k=>$v) {
                $searchKey = array_search($count, $inM);
                if ($searchKey !== false) {
                    $saveKey = 'result'.$searchKey;
                    if (!isset($$saveKey)) $$saveKey = [];
                    $$saveKey[] = $k;
                }
                $count ++;
            }
        }
        $data = [
            $result0,
            $result1,
            $result2,
            $result3,
            $result4,
        ];
        foreach ($data as $key => $value) {
            asort($value);
            foreach ($value as $k=>$v) {
                echo $v. ' ';
            }
            echo PHP_EOL;
        }
        dd();
        return false;
    }
}