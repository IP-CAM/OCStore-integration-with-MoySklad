<?php

namespace MoySklad\Traits;

use MoySklad\Components\Fields\ImageField;
use MoySklad\Entities\AbstractEntity;
use MoySklad\Registers\ApiUrlRegistry;

trait GetImage{
    public function getImage(){
        if(!isset($this->image)) {
            return null;
        }
        
        $imageKey = substr($this->image->miniature->href, 50, -15);

        $image = $this->getSkladInstance()->getClient()->get(
            ApiUrlRegistry::instance()->getMiniatureUrl($imageKey),
            [ 'miniature' => true ]
        );
        
        return $image;
    }
}
