# hyperf-apihelper
hyperf api and swagger helper.   
它是一个[Hyperf](https://github.com/hyperf-cloud/hyperf)框架的 [**api接口自动验证和swagger接口文档生成**] 组件.  
功能包括：  
- 通过注解定义接口路由、请求方法和参数,并由中间件自动校验接口参数.
- 生成json文件,供swagger接口文档测试使用,可打开或关闭.
- swagger支持接口多版本分组管理.
- 支持restful path路由参数校验


### 图例
![api多版本](tests/01.jpg)  

![jwt](tests/02.jpg)  

![jwt](tests/03.jpg)  



### 说明
本组件是参考[apidog](https://github.com/daodao97/apidog)的改写.


### 安装
- 指定站点目录为 BASE_PATH/public,swagger-ui将自动发布到该目录下
- 安装组件
```sh
# 安装依赖组件
composer require kakuilan/hyperf-apihelper

# 发布组件初始化配置文件
php bin/hyperf.php vendor:publish kakuilan/hyperf-apihelper
```



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
- 修改config/autoload/dependencies.php依赖配置,如
```php
return [
    'dependencies' => [
        Hyperf\HttpServer\Router\DispatcherFactory::class => Hyperf\Apihelper\DispathcerFactory::class
    ],
];
```


### 使用
编辑控制器文件app/Controller/Test.php,如
```php
namespace App\Controller;

use Hyperf\Apihelper\Annotation\ApiController;
use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\Apihelper\Annotation\ApiVersion;
use Hyperf\Apihelper\Annotation\Method\Delete;
use Hyperf\Apihelper\Annotation\Method\Get;
use Hyperf\Apihelper\Annotation\Method\Patch;
use Hyperf\Apihelper\Annotation\Method\Post;
use Hyperf\Apihelper\Annotation\Method\Put;
use Hyperf\Apihelper\Annotation\Param\Body;
use Hyperf\Apihelper\Annotation\Param\Form;
use Hyperf\Apihelper\Annotation\Param\Header;
use Hyperf\Apihelper\Annotation\Param\Path;
use Hyperf\Apihelper\Annotation\Param\Query;
use Hyperf\Validation\Validator;

/**
 * @ApiController(tag="测试实例", description="测试例子")
 * @ApiVersion(group="v1", description="第一版本")
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
     * @Patch(path="/info/{id}", description="路由参数测试")
     * @Path(key="id", rule="int|gt:0")
     * @ApiResponse(code=200, schema={"$ref":"Response"})
     */
    public function info() {
        $data = [
            'id' => $this->request->route('id')
        ];
        return ApiResponse::doSuccess($data);
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
- 先执行Hyperf官方规则,详见[hyperf validation](https://hyperf.wiki/#/zh-cn/validation)
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
    
  - 控制器验证方法.  
  若需要在控制器中执行比较复杂的逻辑去验证,则可以使用该方式.  
  如上例中的cb_chkHello,规则名以cb_开头,后跟控制器的方法名chkHello.  
  验证方法必须定义接受3个参数:$value, $field, $options;  
  返回结果必须是一个数组:  
  若检查失败,为[false, 'error msg'];若检查通过,为[true, $newValue],$newValue为参数值的新值.
  

### 响应体结构

- 接口操作成功,返回{"status":true,"msg":"success","code":200,"data":[]}
- 接口操作失败,返回{"status":false,"msg":"fail","code":400,"data":[]}
- 自定义响应体结构,可参考ApiValidationMiddleware和ValidationExceptionHandler,重写你自己的中间件和异常处理.
- 自定义响应错误码code,可参考languages/zh_CN/apihelper.php


### swagger生成

1.  api请求方法定义 `Get`, `Post`, `Put`, `Patch`, `Delete`
2.  参数定义 `Header`, `Query`, `Form`, `Body`, `Path`
3.  返回结果定义 `ApiResponse` ,json串,如{"status":true,"msg":"success","code":200,"data":[]}
4.  ApiVersion接口版本分组并不影响方法里面的实际绑定路由;它只是把控制器里面的接口,归入到某个swagger文件,以便查看.
5.  生产环境请将配置output_json修改为false,关闭swagger.

## TODO
- 多层级参数/body参数的校验
- swagger更多属性的支持



