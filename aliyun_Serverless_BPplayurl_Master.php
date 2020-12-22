y<?php

use RingCentral\Psr7\Response;
/*
To enable the initializer feature (https://help.aliyun.com/document_detail/89029.html)
please implement the initializer function as below：
function initializer($context) {
    echo 'initializing' . PHP_EOL;
}
*/

function handler($request, $context): Response
{
    /*
    $body       = $request->getBody()->getContents();
    $queries    = $request->getQueryParams();
    $method     = $request->getMethod();
    $headers    = $request->getHeaders();
    $path       = $request->getAttribute('path');
    $requestURI = $request->getAttribute('requestURI');
    $clientIP   = $request->getAttribute('clientIP');
    */
    /* Config */

    $upstream_pc_url = 'https://api.bilibili.com/pgc/player/web/playurl';
    $upstream_app_url = 'https://api.bilibili.com/pgc/player/api/playurl';
    $timeout = 5; // seconds
    // 地区不可见code
    $area_limit_code = -10403;
    // 中转地址
    $transfer_address = '';

    /* Read incoming request */
    $request_method = $request->getMethod();
    $request_query = stristr($request->getAttribute("requestURI"), '?');
    $req_referer = $request->getHeaderLine('referer');;
    $request_headers = $request->getHeaders();
    $request_body = $request->getBody()->getContents();


    /* Forward request */
    $ch = curl_init();

    //清理相关header
    array_splice($request_headers, array_search('HOST', $request_headers));
    array_splice($request_headers, array_search('User-Agent', $request_headers));
    array_splice($request_headers, array_search('Referer', $request_headers));


    $headers = array();
    foreach ($request_headers as $key => $value) {
        $headers[] = $key . ': ' . $value;
    }
    //判断使用pc还是app接口
    if (substr_count($request_query, 'platform=android') != 0) {
        $url = $upstream_app_url . $request_query;
        curl_setopt($ch, CURLOPT_USERAGENT, 'Bilibili Freedoooooom/MarkII');
    } else {
        $url = $upstream_pc_url . $request_query;
        curl_setopt($ch, CURLOPT_REFERER, $req_referer);
    }
    //curl配置
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_method);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $header = array();
  
    if ($response === false) {
        $header['Content-Type'] = 'text/plain';
        return new Response(
            502,
            $header,
            'Upstream host did not respond.'
        );
    } else {

        $header_length = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curl_response_headers = explode("\n", substr($response, 0, $header_length));
        $response_body = substr($response, $header_length);

        $body_string = json_decode($response_body, true);
        $status_code = $body_string['code'];
        curl_close($ch);
  // 地区限制，转发香港
    if ($status_code == $area_limit_code) {

    $ch_HK = curl_init();

    $url_HK = $transfer_address . $request_query;
    curl_setopt($ch_HK, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch_HK, CURLOPT_CUSTOMREQUEST, $request_method);
    curl_setopt($ch_HK, CURLOPT_URL, $url_HK);
    curl_setopt($ch_HK, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch_HK, CURLOPT_POSTFIELDS, $request_body);
    curl_setopt($ch_HK, CURLOPT_HEADER, true);
    curl_setopt($ch_HK, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch_HK, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch_HK);

    $header_length = curl_getinfo($ch_HK, CURLINFO_HEADER_SIZE);
    $curl_response_headers = explode("\n", substr($response, 0, $header_length));
    $response_body = substr($response, $header_length);
    curl_close($ch_HK);

    foreach ($curl_response_headers as $header_string) {
        $header_tmp = explode(': ', $header_string, 2);
        if (count($header_tmp) == 2) {
            // 去除多余的Access-Control-Expose-Headers
            if ($header_tmp[0] == 'Access-Control-Expose-Headers') {
                continue;
            }
            $header[$header_tmp[0]] = trim($header_tmp[1]);
        }
    }
        
    return new Response(
        200,
        $header,
        $response_body
    );
    }

        foreach ($curl_response_headers as $header_string) {
            $header_tmp = explode(': ', $header_string, 2);
            if (count($header_tmp) == 2) {
                $header[$header_tmp[0]] = trim($header_tmp[1]);
            }
        }

        // 这行用于调试请求信息
        //return new Response(200, array(), json_encode(array('header' => $header, 'body' => $response_body, 'url' => $url, 'response'=>$response, 'curl_headers'=>$curl_response_headers, 'status_code'=>$status_code)));
        return new Response(
            200,
            $header,
            $response_body
        );
    }
}
