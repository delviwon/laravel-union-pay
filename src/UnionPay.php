<?php

namespace Lewee\UnionPay;

use App\Exceptions\InternalException;
use Carbon\Carbon;
use GuzzleHttp\Client;

class UnionPay
{
    private $base_url = 'https://qr.chinaums.com';
    private $msg_src;
    private $msg_src_id;
    private $mid;
    private $tid;
    private $key;

    /**
     * UnionPay constructor.
     * @throws InternalException
     */
    public function __construct()
    {
        $payment_config = config_item('payment');
        $union_pay_config = $payment_config['union_pay'] ?? null;

        if (
            !$union_pay_config
            || !isset($union_pay_config['msg_src'])
            || !isset($union_pay_config['msg_src_id'])
            || !isset($union_pay_config['mid'])
            || !isset($union_pay_config['tid'])
            || !isset($union_pay_config['key'])
        ) {
            throw new InternalException('缺少支付参数');
        }

        $this->msg_src = $union_pay_config['msg_src'];
        $this->msg_src_id = $union_pay_config['msg_src_id'];
        $this->mid = $union_pay_config['mid'];
        $this->tid = $union_pay_config['tid'];
        $this->key = $union_pay_config['key'];
    }

    /**
     * 发起支付
     * @param $trade_no
     * @param $amount
     * @param null $notify_url
     * @param null $return_url
     * @return mixed
     * @throws InternalException
     */
    public function pay($trade_no, $amount, $notify_url = null, $return_url = null)
    {
        $request_timestamp = Carbon::now()->toDateTimeString();
        $bill_date = Carbon::now()->toDateString();

        $params = [
            'msgSrc' => $this->msg_src,
            'msgType' => 'bills.getQRCode',
            'requestTimestamp' => $request_timestamp,
            'mid' => $this->mid,
            'tid' => $this->tid,
            'instMid' => 'QRPAYDEFAULT',
            'billNo' => $trade_no,
            'merOrderId' => $trade_no,
            'billDate' => $bill_date,
            'totalAmount' => $amount,
        ];

        if ($notify_url) {
            $params['notifyUrl'] = $notify_url;
        }

        if ($return_url) {
            $params['returnUrl'] = $return_url;
        }

        $sign = $this->createSignature($params);
        $params['sign'] = $sign;

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $res = $this->request('POST', "{$this->base_url}/netpay-route-server/api/", $headers, $params);

        if (!isset($res['errCode'])) {
            throw new InternalException('支付请求失败，请稍后再试');
        } else if ($res['errCode'] !== 'SUCCESS') {
            throw new InternalException($res['errMsg']);
        }

        return $res['billQRCode'];
    }

    /**
     * 支付通知
     * @param $callback
     * @return string
     * @throws InternalException
     */
    public function notify($callback)
    {
        $request = request();
        $notify_data = $request->post();
        $signature = $this->createSignature($notify_data);

        if (!$request->post('sign')) {
            throw new InternalException('参数错误');
        }

        if (strtolower($notify_data['sign']) !== $signature) {
            throw new InternalException('通知签名验证失败');
        }

        $callback($request->post('billNo'), $request->post('billStatus'));

        return 'SUCCESS';
    }

    /**
     * 生成签名
     * @param $params
     * @return string
     */
    private function createSignature($params)
    {
        unset($params['sign']);
        ksort($params);
        $sign_str = urldecode(http_build_query($params)) . $this->key;

        return md5($sign_str);
    }

    /**
     * 发送请求
     * @param $method
     * @param $url
     * @param array $headers
     * @param array $params
     * @param string $params_type
     * @return mixed|void
     */
    protected function request($method, $url, $headers = [], $params = [], $params_type = 'json')
    {
        $config = [
            'timeout' => 15,
        ];

        if ($headers) {
            $config['headers'] = $headers;
        }

        switch ($method)
        {
            case 'GET':
                if ($params) {
                    $config['query'] = $params;
                }

                break;
            case 'POST':
                $config[$params_type] = $params;
                break;
            default:
                return;
        }

        try {
            $client = new Client();
            $response = $client->request($method, $url, $config);
            return $this->getResponse($response);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            return $this->getResponse($response);
        }
    }

    /**
     * Get response content
     * @param $response
     * @return mixed
     */
    protected function getResponse($response)
    {
        $contentType = $response->getHeaderLine('Content-Type');
        $contents = $response->getBody()->getContents();

        if (false !== stripos($contentType, 'json') || stripos($contentType, 'javascript')) {
            return json_decode($contents, true);
        } elseif (false !== stripos($contentType, 'xml')) {
            return json_decode(json_encode(simplexml_load_string($contents)), true);
        }
    }
}
