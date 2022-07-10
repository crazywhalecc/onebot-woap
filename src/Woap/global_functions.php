<?php

use OneBot\V12\Object\Action;
use Swoole\Coroutine\Channel;

function message_id(): string
{
    return uniqid('', true);
}

/**
 * 检查微信公众号发来的 HTTP 请求是否合法
 *
 * @param array  $get   GET 请求参数
 * @param string $token 微信公众号设置的 token
 */
function check_wx_signature(array $get, string $token): bool
{
    $signature = $get['signature'] ?? '';
    $timestamp = $get['timestamp'] ?? '';
    $nonce = $get['nonce'] ?? '';
    $tmp_arr = [$token, $timestamp, $nonce];
    sort($tmp_arr, SORT_STRING);
    $tmp_str = implode($tmp_arr);
    $tmp_str = sha1($tmp_str);
    return $signature == $tmp_str;
}

function wx_global_get(string $key)
{
    global $wx_global;
    return $wx_global[$key] ?? null;
}

function wx_global_set(string $key, $value): void
{
    global $wx_global;
    $wx_global[$key] = $value;
}

function wx_global_unset(string $key): void
{
    global $wx_global;
    unset($wx_global[$key]);
}

function wx_global_isset(string $key): bool
{
    global $wx_global;
    return isset($wx_global[$key]);
}

function wx_make_xml_reply(Action $action, string $self_id): string
{
    // TODO: 用新闻页面支持多媒体文本消息
    $xml_template = "\n<xml><ToUserName>{user_id}</ToUserName><FromUserName>{from}</FromUserName><CreateTime>" . time() . '</CreateTime><MsgType>{type}</MsgType><Content>{content}</Content></xml>';
    $xml_template = str_replace('{user_id}', '<![CDATA[' . $action->params['user_id'] . ']]>', $xml_template);
    $xml_template = str_replace('{from}', '<![CDATA[' . $self_id . ']]>', $xml_template);
    $xml_template = str_replace('{type}', '<![CDATA[text]]>', $xml_template);
    $content = '';
    foreach ($action->params['message'] as $v) {
        if ($v['type'] !== 'text') {
            return str_replace('{content}', '<![CDATA[*含有多媒体消息，暂不支持*]]>', $xml_template);
        }
        $content .= $v['data']['text'];
    }

    return str_replace('{content}', '<![CDATA[' . $content . ']]>', $xml_template);
}

function swoole_channel(string $name, int $size = 1): Channel
{
    global $channel;
    if (!isset($channel[$name])) {
        $channel[$name] = new Channel($size);
    }
    return $channel[$name];
}