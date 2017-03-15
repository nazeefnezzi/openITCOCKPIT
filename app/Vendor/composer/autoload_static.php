<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitc59c4036cad82e65a198aa10e4f1f83f
{
    public static $prefixLengthsPsr4 = array (
        'i' => 
        array (
            'itnovum\\openITCOCKPIT\\' => 22,
        ),
        'S' => 
        array (
            'Symfony\\Component\\Filesystem\\' => 29,
        ),
        'A' => 
        array (
            'Adldap\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'itnovum\\openITCOCKPIT\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/itnovum/openITCOCKPIT',
        ),
        'Symfony\\Component\\Filesystem\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/filesystem',
        ),
        'Adldap\\' => 
        array (
            0 => __DIR__ . '/..' . '/adldap/adldap/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitc59c4036cad82e65a198aa10e4f1f83f::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitc59c4036cad82e65a198aa10e4f1f83f::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
