<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 16:17
 * Desc: body数据注解
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
        $this->required = strpos(json_encode($this->rules), 'required') !== false;
        return $this;
    }

    public function setType() {
        $this->type = '';
        return $this;
    }
}