<?php

class Bootstrap extends \Yaf\Bootstrap_Abstract {

    public function _initLoader (\Yaf\Dispatcher $dispatcher) {
        Yaf\Loader::getInstance()->registerLocalNameSpace(['library','services','define']);
    }

    public function _initPlugin( \Yaf\Dispatcher $dispatcher ) {
    }

    public function _initRoute( \Yaf\Dispatcher $dispatcher ) {
    }
    public function _initView(Yaf\Dispatcher $dispatcher){
        $dispatcher->disableView();
    }
}
