<?php
class Base
{
    protected function echo($string)
    {
        echo $string.PHP_EOL;
    }

    protected function get($url, $file='')
    {
        return $this->doUrl($url, $file);
    }

    protected function post($url, $file)
    {
        return $this->doUrl($url, $file, 'post');
    }

    protected function doUrl($url, $file='', $method='get')
    {
        for ($sRun = 0; $sRun < 9; $sRun ++)
        { 
            if ($file && is_file($file)) {
                return true;
            }
            try
            {
                $oGuzzle = $this->oClient->$method($url);
                $this->oCookie = new \GuzzleHttp\Cookie\CookieJar();
                if (200 == $oGuzzle->getStatusCode())
                {
                    $sGuzzle = $oGuzzle->getBody()->getContents();
                    if ($this->initFile($file)) {
                        file_put_contents($file, $sGuzzle);
                    }
                    $this->echo('数据下载完成!!');
                    break;
                }
            } catch (\GuzzleHttp\Exception\ConnectException $e) {
                $this->echo('连接错误');
                $sGuzzle = false;
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $this->echo('guzzle 错误');
                $sGuzzle = false;
            } 
        }
        return $sGuzzle;
    }

    protected function initFile($file)
    {
        if (empty($file)) {
            return false;
        }
        existsOrCreate($file);
        return true;
    }
}