# hyperf-apihelper
hyperf api swagger helper.   
**api接口自动验证,swagger接口文档**   
通过注解定义接口相关路径和参数,本中间件将自动验证接口参数;并生成json,供swagger接口文档测试使用.


### 说明
本组件是参考[apidog](https://github.com/daodao97/apidog)的改写


### 安装
- 安装组件
```sh
composer require kakuilan/hyperf-apihelper
php bin/hyperf.php vendor:publish kakuilan/hyperf-apihelper
```

- 安装swagger-ui  
下载[swagger-ui](https://github.com/swagger-api/swagger-ui)并解压,拷贝dist目录中的文件到应用项目目录public/swagger


### 配置
- 修改config/autoload/swagger.php中的配置,将host改为你的域名,如test.com,则接口文档地址为test.com/swagger
- 修改config/autoload/middlewares.php中间件配置,如
```php
return [
    'http' => [
        Hyperf\Apihelper\Middleware\ApiValidationMiddleware,
    ],
];
```
- 修改config/autoload/exceptions.php异常处理配置,如
```php
return [
    'handler' => [
        'http' => [
            Hyperf\Apihelper\Exception\Handler\ValidationExceptionHandler::class,
            App\Exception\Handler\AppExceptionHandler::class,
        ],
    ],
];
```


### 使用
编辑控制器文件app/Controller/Test.php,如
```php
namespace App\Controller;

use Hyperf\Apihelper\Annotation\ApiController;
use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\Apihelper\Annotation\Method\Delete;
use Hyperf\Apihelper\Annotation\Method\Get;
use Hyperf\Apihelper\Annotation\Method\Post;
use Hyperf\Apihelper\Annotation\Method\Put;
use Hyperf\Apihelper\Annotation\Param\Body;
use Hyperf\Apihelper\Annotation\Param\Form;
use Hyperf\Apihelper\Annotation\Param\Header;
use Hyperf\Apihelper\Annotation\Param\Query;
use Hyperf\Validation\Validator;

/**
 * @ApiController(tag="测试实例", description="测试例子")
 */
class Test extends AbstractController {

    /**
     * @Get(path="/user", description="获取用户详情")
     * @Query(key="id", rule="required|int|gt:0")
     * @Query(key="u", rule="required|active_url|trim")
     * @Query(key="t", rule="required|starts_with:a")
     * @Query(key="e", rule="required|trim|enum:e,f,g")
     * @Query(key="h", rule="trim|cb_chkHello:4,5,6")
     * @ApiResponse(code=200, schema={"$ref":"Response"})
     */
    public function get() {
        //return ApiResponse::doFail([]);
        return ApiResponse::doSuccess([]);
    }


    /**
     * 检查输入字段
     * @param mixed $value 字段值
     * @param string $field 字段名
     * @param array $options 参数选项
     * @return array
     */
    public function chkHello($value, string $field, array $options) {
        $res = [
            true,
            $value,
        ];
        if(!true) {
            $res = [
                false,
                '具体验证失败的信息',
            ];
        }

        return $res;
    }

}
```


### 验证规则
- 先执行**Hyperf官方规则**,见[hyperf validation](https://hyperf.wiki/#/zh-cn/validation)
- 再执行本组件的验证规则,包括:  
  - 转换器,有
    - int/integer,将参数值转换为整型
    - float,将参数值转换为浮点数
    - bool,将参数值转换为布尔型
    - trim,过滤参数值前后空格
    
  - 扩展规则,有
    - safe_password,检查是否安全密码
    - natural,检查是否自然数
    - cnmobile,检查是否中国手机号
    - enum,检查参数值是否枚举值中的一个
    
  - 控制器验证方法.若需要在控制器中执行比较复杂的逻辑去验证,则可以使用该方式.  
  如上例中的cb_chkHello,规则名以cb_开头,后跟控制器的方法名chkHello.  
  验证方法必须定义接受3个参数:$value, $field, $options;  
  返回结果必须是一个数组:若检查失败,为[false, 'error msg'];若检查通过,为[true, $newValue],$newValue为参数值的新值.
  

### 响应体结构
- 接口操作成功,返回{"status":true,"msg":"success","code":200,"data":[]}
- 接口操作失败,返回{"status":false,"msg":"fail","code":400,"data":[]}
- 自定义你自己的响应体结构,可参考ApiValidationMiddleware和ValidationExceptionHandler,重写你自己的中间件和异常处理.


### swagger生成

1.  api请求方法定义 `Get`, `Post`, `Put`, `Delete`
2.  参数定义 `Header`, `Query`, `Form`, `Body`, `Path`
3.  返回结果定义 `ApiResponse` ,json串,如{"status":true,"msg":"success","code":200,"data":[]}


## TODO
- 多层级参数/body参数的校验
- swagger更多属性的支持
- restful 路径中的路由参数处理
- 接口多版本处理,如v1,v2

