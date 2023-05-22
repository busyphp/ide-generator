<?php
declare(strict_types = 1);

namespace BusyPHP\ide\generator;

use Psr\EventDispatcher\EventDispatcherInterface;
use think\facade\Event;

/**
 * 事件调度类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2023 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2023/5/22 16:09 EventDispatcher.php $
 */
class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @inheritDoc
     */
    public function dispatch(object $event)
    {
        Event::trigger($event);
    }
}