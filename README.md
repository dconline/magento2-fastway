该项目是Magento2的Fastway物流插件,通过调用Fastway的API,计算运费并呈现在Magento2网站中，该插件目前适用于南非.

## 功能
1. 计算运费
2. 查询物流信息
3. Fastway单号验证

## 依赖环境
1. PHP7以上
2. Magento2安装方法请看:
    - https://github.com/magento/magento2

## 安装
1. 获取访问Fastway的key,浏览器打开网址：http://sa.api.fastway.org/v3/docs/page/GetAPIKey.html, 填入基本信息并提交,留意邮箱内是否收到Fastway发送的邮件，邮件内会包含Key.

2. 使用Composer(推荐)
    - composer require dconline/module-fastway

3. 启用插件
    - php -f bin/magento module:enable --clear-static-content DCOnline_Fastway
    - php -f bin/magento setup:upgrade
    - php -f bin/magento setup:static-content:deploy
    - php -f bin/magento cache:flush

## 配置
1. 登录Magento2网站后台,进入到运输插件配置的菜单:Stores——>Settings——>Configuration——>Sales——>Shipping Methods.
2. 选择Fastway,把第一步获取的Key填入到输入框中.
3. 国家选择南非，其它选项和输入项默认即可.
4. 刷新网站索引:
    - php -f bin/magento cache:flush

