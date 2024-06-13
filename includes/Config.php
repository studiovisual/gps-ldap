<?php
namespace Ldap;

$configs = array();

class Config {
    function __construct(){
        $this->configs = [
            'gps-pamcary' => [
                'account_suffix' => '',
                'base_dn' => 'OU=BR,DC=gps-pamcary,DC=local',
                #'domain_controllers' => ['172.22.32.7'],
                'domain_controllers' => ['10.10.0.120'],
                'admin_username' => 'gps-pamcary\SRV_LDAP',
                'admin_password' => 'P@ssw0rd',
                'recursive_groups' => 'false',
                'ad_port' => '389',
                'groups' => [
                    ['BEL', 'Users'],
                    ['BHZ', 'Users'],
                    ['BHZ-R', 'Users'],
                    ['BLU', 'Users'],
                    ['CBA', 'Users'],
                    ['CTB', 'Users'],
                    ['CTB-R', 'Users'],
                    ['GNA', 'Users'],
                    ['OSC', 'Users'],
                    ['POA', 'Users'],
                    ['PTV', 'Users'],
                    ['REC', 'Users'],
                    ['REC-R', 'Users'],
                    ['RJOc', 'Users'],
                    ['RJOr', 'Users'],
                    ['SPO', 'MATRIZ', 'IT', 'Users Especiais'],
                    ['SPO', 'MATRIZ', 'Users'],
                    ['SPO', 'VIP', 'Users'],
                    ['SVD', 'Users'],
                    ['UBL', 'Users'],
                    ['VIT', 'Users'],
                    ['VIT-R', 'Users'],
                    ['VMA', 'Users'],
                    ['HOME_OFFICE', 'Users'],
                ]
            ],
        
            'telerisco' => [
                'account_suffix' => '',
                'base_dn' => 'OU=BR,DC=telerisco-sa,DC=local',
                #'domain_controllers' => ['172.22.32.4'],
                'domain_controllers' => ['192.168.23.4'],
                'admin_username' => 'telerisco-sa\SRV_LDAP',
                'admin_password' => 'Telerisco@2022',
                'recursive_groups' => 'false',
                'ad_port' => '389',
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