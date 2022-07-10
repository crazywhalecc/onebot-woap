<?php

namespace Woap;

use DOMDocument;
use OneBot\Driver\Event\EventProvider;
use OneBot\Driver\Event\Http\HttpRequestEvent;
use OneBot\Http\HttpFactory;
use OneBot\V12\Config\Config;
use OneBot\V12\Exception\OneBotException;
use OneBot\V12\Object\Action;
use OneBot\V12\Object\Event\Message\PrivateMessageEvent;
use OneBot\V12\Object\MessageSegment;
use OneBot\V12\OneBot;
use OneBot\V12\OneBotBuilder;

class Woap
{
    private OneBot $onebot;

    private Config $config;

    /**
     * @throws OneBotException
     */
    public static function createFromConfig(array $config): Woap
    {
        return new static(OneBotBuilder::buildFromArray($config));
    }

    /**
     * @throws OneBotException
     */
    public function __construct(OneBot $onebot)
    {
        $this->onebot = $onebot;
        $this->config = $onebot->getConfig();
        EventProvider::addEventListener(HttpRequestEvent::getName(), [$this, 'addWXEventListener']);
        $this->onebot->setActionHandlerClass(WoapActionHandler::class);
    }

    public function run()
    {
        $this->onebot->run();
    }

    /**
     * @throws OneBotException
     * @internal
     */
    public function addWXEventListener(HttpRequestEvent $event)
    {
        if ($event->getSocketFlag() !== 1000) {
            return;
        }

        // 检查是否为微信的签名
        if (!check_wx_signature($event->getRequest()->getQueryParams(), $this->config->get('wx.token'))) {
            $event->withResponse(HttpFactory::getInstance()->createResponse(403));
            return;
        }

        // 如果是echostr认证，则直接返回
        if (isset($event->getRequest()->getQueryParams()['echostr'])) {
            $event->withResponse(HttpFactory::getInstance()->createResponse(200, null, [], $event->getRequest()->getQueryParams()['echostr']));
            return;
        }

        // 解析 XML 包体
        $xml_data = $event->getRequest()->getBody()->getContents();
        ob_logger()->info($xml_data);
        $xml_tree = new DOMDocument('1.0', 'utf-8');
        $xml_tree->loadXML($xml_data);
        $msg_type = $xml_tree->getElementsByTagName('MsgType')->item(0)->nodeValue;
        $self_id = $xml_tree->getElementsByTagName('ToUserName')->item(0)->nodeValue;
        if (OneBot::getInstance()->getSelfId() === '') {
            OneBot::getInstance()->setSelfId($self_id);
        }
        $user_id = $xml_tree->getElementsByTagName('FromUserName')->item(0)->nodeValue;
        switch ($msg_type) {
            case 'text':
                $content = $xml_tree->getElementsByTagName('Content')->item(0)->nodeValue;
                $msg_event = new PrivateMessageEvent($user_id, MessageSegment::createFromString($content));
                break;
            case 'image':
                $pic_url = $xml_tree->getElementsByTagName('PicUrl')->item(0)->nodeValue;
                $pic_url = openssl_encrypt($pic_url, 'AES-128-ECB', OneBot::getInstance()->getConfig()->get('wx.aeskey'));
                $seg = new MessageSegment('image', ['file_id' => $pic_url]);
                $msg_event = new PrivateMessageEvent($user_id, [$seg]);
                break;
            case 'event':
                $content = $xml_tree->getElementsByTagName('Event')->item(0)->nodeValue;
                break;
            case 'voice':
                $content = preg_replace('/[，。]/', '', $xml_tree->getElementsByTagName('Recognition')->item(0)->nodeValue);
                $msg_event = new PrivateMessageEvent($user_id, MessageSegment::createFromString($content));
                break;
            default:
                echo $xml_data . PHP_EOL;
        }

        if (!isset($msg_event)) {
            $event->withResponse(HttpFactory::getInstance()->createResponse(204));
            return;
        }
        // 设置 message_id，因为微信公众号事件中自带 MsgId，所以直接传递
        $msg_event->message_id = $xml_tree->getElementsByTagName('MsgId')->item(0)->nodeValue;

        // 然后分别判断 swoole 还是 workerman（处理方式不同
        // Swoole 处理时候直接用 Channel，这里为消费者，限定 4.5 秒内拿到一个回包，否则就不回复
        if ($this->onebot->getDriver()->getName() === 'swoole') { // Swoole 用协程，因为 Swoole 下如果不用协程挂起的话，空返回直接 500
            if (swoole_channel($self_id . ':' . $user_id)->stats()['queue_num'] !== 0) {
                swoole_channel($self_id . ':' . $user_id)->pop(4.5);
            }
            wx_global_set($self_id . ':' . $user_id, true);
            // 首先调用 libob 内置的分发函数，通过不同的通信方式进行事件分发
            OneBot::getInstance()->dispatchEvent($msg_event);
            // 这段为临时的调试代码，模拟一个固定的发送消息动作
            /* switch ($msg_event->message[0]->type) {
                case 'text':
                    $content = '收到了一条消息: ' . $msg_event->message[0]->data['text'];
                    break;
                case 'image':
                    $content = '123';
                    break;
                case 'voice':
                    $content = '收到了一条语音消息';
                    break;
                default:
                    $content = '没';
            }
            OneBotEventListener::getInstance()->processActionRequest(json_encode([
                'action' => 'send_message',
                'params' => [
                    'user_id' => $user_id,
                    'self_id' => $self_id,
                    'detail_type' => 'private',
                    'message' => [
                        [
                            'type' => 'text',
                            'data' => [
                                'text' => $content,
                            ],
                        ],
                    ],
                ],
            ])); */
            $obj = swoole_channel($self_id . ':' . $user_id)->pop(4.5); // 等待4.5秒后，如果还不返回，就失败
            wx_global_unset($self_id . ':' . $user_id);
            if ($obj === false) {
                $event->withResponse(HttpFactory::getInstance()->createResponse(204));
                return;
            }
            if ($obj instanceof Action) {
                $xml = wx_make_xml_reply($obj, $self_id);
                $event->withResponse(HttpFactory::getInstance()->createResponse(200, null, ['Content-Type' => 'application/xml'], $xml));
            }
        } elseif ($this->onebot->getDriver()->getName() === 'workerman') { // Workerman 使用异步模式，直接把回调存起来，然后等触发
            $event->setAsyncSend(); // 标记为异步发送
            $timer_id = $this->onebot->getDriver()->addTimer(4500, function () use ($event, $self_id, $user_id) {
                $event->getAsyncSendCallable()(HttpFactory::getInstance()->createResponse(204));
                wx_global_unset($self_id . ':' . $user_id);
            });
            wx_global_set($self_id . ':' . $user_id, function (Action $action) use ($event, $timer_id, $self_id) {
                $xml = wx_make_xml_reply($action, $self_id);
                $event->getAsyncSendCallable()(HttpFactory::getInstance()->createResponse(200, null, ['Content-Type' => 'application/xml'], $xml));
                $this->onebot->getDriver()->clearTimer($timer_id);
            });
            OneBot::getInstance()->dispatchEvent($msg_event);
        } else {
            $event->withResponse(HttpFactory::getInstance()->createResponse(500, 'Unknown Driver'));
        }
    }
}