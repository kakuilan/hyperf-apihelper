<?php
/**
 * Copyright (c) 2020 LKK All rights reserved
 * User: kakuilan
 * Date: 2020/3/9
 * Time: 15:42
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Annotation\Param;

use Hyperf\Apihelper\Annotation\Params;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Body extends Params {

    const NAME = 'body';

    public $in = 'body';


    /**
     * Body constructor.
     * @param null $value
     */
    public function __construct($value = null) {
        parent::__construct($value);

        $this->setKey(self::NAME)->setName(self::NAME)->setDescription(self::NAME)->setRquire()->setType();
    }


    /**
     * @return $this|Params
     */
    public function setRquire() {
        $this->required = strpos($this->rule, 'required') !== false;
        return $this;
    }


    /**
     * @return $this|Params
     */
    public function setType() {
        $this->type = '';
        return $this;
    }

}