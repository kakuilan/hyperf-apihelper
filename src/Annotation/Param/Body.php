<?php
/**
 * Created by PhpStorm.
 * User: Administrator
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

    public $in = 'body';

    public function __construct($value = null) {
        parent::__construct($value);

        $this->setName('body')->setDescription('body')->setRquire()->setType();
    }


    public function setRquire() {
        $this->required = strpos($this->rule, 'required') !== false;
        return $this;
    }


    public function setType() {
        $this->type = '';
        return $this;
    }
}