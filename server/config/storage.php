<?php
/**
 * @desc storage.php
 *
 */

return [
	'default' => 'local', // local：本地 oss：阿里云 cos：腾讯云 qos：七牛云
	'single_limit' => 1024 * 1024 * 200, // 单个文件的大小限制，默认200M 1024 * 1024 * 200
	'total_limit' => 1024 * 1024 * 200, // 所有文件的大小限制，默认200M 1024 * 1024 * 200
	'nums' => 10, // 文件数量限制，默认10
	'include' => ['png' , 'jpg' ,'gif'], // 被允许的文件类型列表
	'exclude' => [], // 不被允许的文件类型列表
	
	'mime_whitelist' => [
		'image/jpeg',
		'image/png',
		'text/plain',
	],
	
	// 本地对象存储
	'local' => [
		'adapter' => \Framework\Storage\Adapter\LocalAdapter::class,
		'root' => BASE_PATH.'/public/uploads/',
		'dirname' => date('Y-m-d'),
		'domain' => env('APP_URL', 'http://localhost:8000'),
		'uri' => '/api/uploads',
		'algo' => 'sha1',
	],
	// 阿里云对象存储
	'oss' => [
		'adapter' => \Framework\Storage\Adapter\OssAdapter::class,
		'accessKeyId' => 'xxxxxxxxxxxx',
		'accessKeySecret' => 'xxxxxxxxxxxx',
		'bucket' => 'resty-files',
		'dirname' => 'storage',
		'domain' => 'http://files.oss.xxxx.com',
		'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
		'algo' => 'sha1',
	],
	// 腾讯云对象存储
	'cos' => [
		'adapter' => \Framework\Storage\Adapter\CosAdapter::class,
		'secretId' => 'xxxxxxxxxxxxx',
		'secretKey' => 'xxxxxxxxxxxx',
		'bucket' => 'resty-files-xxxxxxxxx',
		'dirname' => 'storage',
		'domain' => 'http://files.oss.xxxx.com',
		'region' => 'ap-shanghai',
	],
	// 七牛云对象存储
	'qiniu' => [
		'adapter' => \Framework\Storage\Adapter\QiniuAdapter::class,
		'accessKey' => 'xxxxxxxxxxxxx',
		'secretKey' => 'xxxxxxxxxxxxx',
		'bucket' => 'resty-files',
		'dirname' => 'storage',
		'domain' => 'http://files.oss.xxxx.com',
	],
	// aws
	's3' => [
		'adapter' => \Framework\Storage\Adapter\S3Adapter::class,
		'key' => 'xxxxxxxxxxxxx',
		'secret' => 'xxxxxxxxxxxxx',
		'bucket' => 'resty-files',
		'dirname' => 'storage',
		'domain' => 'http://files.oss.xxxx.com',
		'region' => 'S3_REGION',
		'version' => 'latest',
		'use_path_style_endpoint' => true,
		'endpoint' => 'S3_ENDPOINT',
		'acl' => 'public-read',
	],

];
