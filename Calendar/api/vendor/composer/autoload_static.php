<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit046912947ae038adcfb7f7fbecc19e21
{
    public static $files = array (
        '1f87db08236948d07391152dccb70f04' => __DIR__ . '/..' . '/google/apiclient-services/autoload.php',
        'decc78cc4436b1292c6c0d151b19445c' => __DIR__ . '/..' . '/phpseclib/phpseclib/phpseclib/bootstrap.php',
        'a8d3953fd9959404dd22d3dfcd0a79f0' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
    );

    public static $prefixLengthsPsr4 = array (
        'p' => 
        array (
            'phpseclib3\\' => 11,
        ),
        'R' => 
        array (
            'RRule\\' => 6,
        ),
        'P' => 
        array (
            'Psr\\Log\\' => 8,
            'Psr\\Http\\Message\\' => 17,
            'Psr\\Cache\\' => 10,
            'ParagonIE\\ConstantTime\\' => 23,
        ),
        'M' => 
        array (
            'Monolog\\' => 8,
        ),
        'G' => 
        array (
            'Google\\Service\\' => 15,
            'Google\\Auth\\' => 12,
            'Google\\' => 7,
        ),
        'F' => 
        array (
            'Firebase\\JWT\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'phpseclib3\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpseclib/phpseclib/phpseclib',
        ),
        'RRule\\' => 
        array (
            0 => __DIR__ . '/..' . '/rlanvin/php-rrule/src',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'Psr\\Cache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/cache/src',
        ),
        'ParagonIE\\ConstantTime\\' => 
        array (
            0 => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src',
        ),
        'Monolog\\' => 
        array (
            0 => __DIR__ . '/..' . '/monolog/monolog/src/Monolog',
        ),
        'Google\\Service\\' => 
        array (
            0 => __DIR__ . '/..' . '/google/apiclient-services/src',
        ),
        'Google\\Auth\\' => 
        array (
            0 => __DIR__ . '/..' . '/google/auth/src',
        ),
        'Google\\' => 
        array (
            0 => __DIR__ . '/..' . '/google/apiclient/src',
        ),
        'Firebase\\JWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Google_AccessToken_Revoke' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_AccessToken_Verify' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_AuthHandler_AuthHandlerFactory' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_AuthHandler_Guzzle5AuthHandler' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_AuthHandler_Guzzle6AuthHandler' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_AuthHandler_Guzzle7AuthHandler' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Client' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Collection' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Exception' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Http_Batch' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Http_MediaFileUpload' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Http_REST' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Model' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Service' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Service_Exception' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Service_Resource' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Task_Composer' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Task_Exception' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Task_Retryable' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Task_Runner' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
        'Google_Utils_UriTemplate' => __DIR__ . '/..' . '/google/apiclient/src/aliases.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit046912947ae038adcfb7f7fbecc19e21::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit046912947ae038adcfb7f7fbecc19e21::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit046912947ae038adcfb7f7fbecc19e21::$classMap;

        }, null, ClassLoader::class);
    }
}
