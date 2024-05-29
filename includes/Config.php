<?php
namespace Ldap;

public $configs = array();

class Config {
    function __construct(){
        $this->configs = [
            'gps-pamcary' => [
                'hosts'     => ['10.10.0.120'],
                'base_dn'   => 'OU=BR,DC=gps-pamcary,DC=local',
                'username'  => 'gps-pamcary\SRV_LDAP',
                'password'  => 'P@ssw0rd',
                'port'      => 389,
            ],
            'telerisco' => [
                'hosts'     => ['192.168.23.4'],
                'base_dn'   => 'OU=BR,DC=telerisco-sa,DC=local',
                'username'  => 'telerisco-sa\SRV_LDAP',
                'password'  => 'Telerisco@2022',
                'port'      => 389,
            ], 
        /* 'roadcard' => [
                'account_suffix' => '',
                'base_dn' => 'OU=BR,DC=roadcard,DC=local',
                'domain_controllers' => ['172.22.34.80'],
                'admin_username' => 'roadcard\SRV_LDAP',
                'admin_password' => 'P@ssw0rd',
                'recursive_groups' => 'false',
                'ad_port' => '389',
            ],*/
        ];

        return $this->configs;
    }
}