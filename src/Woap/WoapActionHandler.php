<?php

namespace Woap;

use Closure;
use OneBot\Util\Validator;
use OneBot\V12\Action\ActionBase;
use OneBot\V12\Action\ActionResponse;
use OneBot\V12\Object\Action;
use OneBot\V12\OneBot;
use OneBot\V12\RetCode;

class WoapActionHandler extends ActionBase
{
    public function onSendMessage(Action $action): ActionResponse
    {
        Validator::validateParamsByAction($action, ['user_id' => true, 'message' => true]);
        Validator::validateMessageSegment($action->params['message']);
        if (OneBot::getInstance()->getDriver()->getName() === 'swoole') {
            $channel_name = ($action->params['self_id'] ?? OneBot::getInstance()->getSelfId()) . ':' . $action->params['user_id'];
            if (wx_global_isset($channel_name)) {
                $a = swoole_channel($channel_name)->push($action, 4.5);
                if ($a === false) {
                    return ActionResponse::create($action->echo)->fail(34001, 'Wechat MP API cannot send message directly');
                }
                return ActionResponse::create($action->echo)->ok(['message_id' => message_id(), 'time' => time()]);
            }
            return ActionResponse::create($action->echo)->fail(34001, 'Wechat MP API cannot send message directly for now');
        }
        if (OneBot::getInstance()->getDriver()->getName() === 'workerman') {
            $channel_name = ($action->params['self_id'] ?? OneBot::getInstance()->getSelfId()) . ':' . $action->params['user_id'];
            if (wx_global_isset($channel_name)) {
                $a = wx_global_get($channel_name);
                if ($a instanceof Closure) {
                    $a($action);
                    return ActionResponse::create($action->echo)->ok(['message_id' => message_id(), 'time' => time()]);
                }
                return ActionResponse::create($action->echo)->fail(34001, 'Wechat MP API cannot send message directly for now');
            }
            return ActionResponse::create($action->echo)->fail(34001, 'Wechat MP API cannot send message directly for now');
        }
        return ActionResponse::create($action->echo)->fail(RetCode::INTERNAL_HANDLER_ERROR);
    }
}