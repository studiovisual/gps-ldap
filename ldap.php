<?php
/*
*   Plugin Name: Ldap
*   Author: Studio Visual
*   Author URI: https://studiovisual.com.br 
*   Description: Plugin de sincronização do LDAP da GPS NET
*   Version: 1.0
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if(file_exists(plugin_dir_path( __FILE__ ) . 'vendor/autoload.php'))
	require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
else {
	die("Can't load composer.");
}

use Adldap\Adldap;

new Ldap\AdminPage();

//DESCOMENTAR
//Redirect users to login on load, excluding uploads folder
add_action( 'init', function() {
    global $pagenow;
    if (strpos($_SERVER['REQUEST_URI'], 'uploads') == false) {
        if(!is_user_logged_in() && $pagenow != 'wp-login.php')
            auth_redirect();
    }
});

add_filter('authenticate', function ($user) {
    if (isset($_POST['log']) && $_POST['pwd']) {
        $user = get_user_by('login', $_POST['log']);

        if ($user && get_user_meta($user->ID, 'ldap_login', true)) {
            $ldap = new Ldap\Config();
            $ldap->configs['gps-pamcary']['admin_username'] = "gps-pamcary\\" . $_POST["log"];
            $ldap->configs['gps-pamcary']['admin_password'] = $_POST["pwd"];
            
            $ad = new Adldap\Adldap($ldap->configs['gps-pamcary']);
            if ($ad->connect()) {
                $active = get_user_meta($user->ID, 'lus_active', true);
                if ($active != '1') {
                    return false;
                }
                return $user;
            }
        }
    }

    if ($user && wp_check_password($_POST['pwd'], $user->data->user_pass, $user->ID)) {
        return $user;
    }

    return false;
});

Ldap\PostRequest::register('user_ldap', function () {
    if (!isset($_POST['email']) || empty($_POST['email'])) {
        echo 'E-mail obrigatório.';
        exit;
    }

    $ldap = new Ldap\Config();
    $ad = new Adldap($ldap->configs['gps-pamcary']);
    $ad->connect();

    $groups = $ldap->configs['gps-pamcary']['groups'];

    $userArray = [];

    foreach ($groups as $group) {
        $users = $ad->folder()->listing($group);
        $userArray = array_merge($userArray, $users);
    }

    $users = $userArray;
    $userKey = null;

    $email = isset($_POST['email']) && !empty($_POST['email']) ? strtolower($_POST['email']) : false;

    foreach ($users as $key => $val) {
        if ($val['mail'] === $email) {
            $userKey =  $key;
            break;
        }
    }

    $usuarios_editados = [];

    global $wpdb;

    if ($userKey != null) {
        $user = $users[$userKey];

        $name = $user['givenname'];
        $surname = $user['sn'];
        $username = $user['samaccountname'];

        if (function_exists('mb_convert_case')) {
            $name = mb_convert_case(strtolower($user['givenname']), MB_CASE_TITLE, 'UTF-8');
            $surname = mb_convert_case(strtolower($user['sn']), MB_CASE_TITLE, 'UTF-8');
        }

        #Verifica usuário por email - alterado para buscar pelo usuario
        $userObj = get_user_by('login', $username);

        if ($userObj) {
            if (updateUserLdap($userObj, $user)) {
                echo 'Usuário atualizado com sucesso';
                exit;
            } else {
                echo 'Usuário bloqueado ou não ativo (status 513 ou 514), ou não possui ramal no AD portanto, removido do sistema.';
                exit;
            }
        } else {
            $newUserId = wp_insert_user([
                'user_pass' => wp_generate_password(12, true, true),
                'user_login' => $username,
                'user_nicename' => $email,
                'user_email' => $email,
                'display_name' => $name,
                'nickname' => $name,
                'first_name' => $name,
                'last_name' => $surname,
                'user_registered' => date("Y-m-d H:i:s")
            ]);

            if (is_wp_error($newUserId)) {
                echo 'Erro ao criar usuário na intranet';
                exit;
            }

            $userObj = get_user_by('ID', $newUserId);
            if (updateUserLdap($userObj, $user)) {
                echo 'Usuário atualizado com sucesso';
                exit;
            } else {
                echo 'Usuário bloqueado ou não ativo (status 513 ou 514) no AD';
                exit;
            }

            echo 'Usuário criado na intranet!';
            exit;
        }
    } else {
        echo 'E-mail não encontrado no AD!';
        exit;
    }

    die();
}, []);

function updateUserLdap($userObj, $userLdap)
{
    global $wpdb;

    // Vai entrar caso o usuário para o e-mail já exista no banco de dados mas é diferente do que veio no LDAP
    if ($userObj->user_login != $userLdap['samaccountname']) {
        $numero = 1;

        $userLogin = $userObj->user_login;

        while ($userExistente = get_user_by('login', $userLogin)) {
            $userLogin = $userLogin . '-' . $numero;
            $numero++;
        }

        // Dá ao usuário que já existe com o mesmo username, um novo username
        if ($userExistente) {
            $wpdb->update(
                $wpdb->users,
                ['user_login' => $userLogin],
                ['ID' => $userExistente->ID]
            );
        }

        // Atualiza o usuário atual com o username correto
        $wpdb->update(
            $wpdb->users,
            ['user_login' => $userLdap['samaccountname']],
            ['ID' => $userObj->ID]
        );
    }

    // Atualização cadastral. Verifica se o usuário está no grupo de BLOQUEADOS ou EXTERNOS
    // e faz a remoção do banco. Também verifica se o usuário está com o status 512 (Ativo)
    // Caso não tenha ramal (pager) também remove do banco.
    updateUserMetaLdap($userObj, $userLdap, !checkUserBlocked($userLdap));

    // Remove o usuário da base
    if (checkUserBlocked($userLdap) && isset($userObj->ID) && !is_wp_error($userObj->ID)) {
        return false;
    }

    return true;
}

function checkUserBlocked($userLdap)
{
    foreach ($userLdap['dn_array'] as $dn) {
        if ($dn == 'BLOQUEADOS' || $dn == 'EXTERNOS') {
            return true;
        }
    }

    if (!isset($userLdap['pager']) || empty($userLdap['pager']) || $userLdap['useraccountcontrol'] == "513" || $userLdap['useraccountcontrol'] == "514") {
        return true;
    }

    return false;
}

function updateUserMetaLdap($userObj, $userLdap, $active = 1)
{
    update_user_meta($userObj->ID, 'lus_active',           $active);
    update_user_meta($userObj->ID, 'lus_name',             isset($userLdap['cn'])              ? $userLdap['cn'] : '');
    update_user_meta($userObj->ID, 'lus_telephonenumber',  isset($userLdap['telephonenumber']) ? $userLdap['telephonenumber'] : '');
    update_user_meta($userObj->ID, 'lus_pager',            isset($userLdap['pager'])           ? $userLdap['pager'] : '');
    update_user_meta($userObj->ID, 'lus_st',               isset($userLdap['st'])              ? $userLdap['st'] : '');
    update_user_meta($userObj->ID, 'lus_department',       isset($userLdap['department'])      ? $userLdap['department'] : '');
    update_user_meta($userObj->ID, 'lus_department_floor', '');
    update_user_meta($userObj->ID, 'lus_streetaddress',    isset($userLdap['streetaddress'])   ? $userLdap['streetaddress'] : '');
    update_user_meta($userObj->ID, 'lus_houseidentifier',  isset($userLdap['houseidentifier']) ? $userLdap['houseidentifier'] : '');
    update_user_meta($userObj->ID, 'lus_l',                isset($userLdap['l'])               ? $userLdap['l'] : '');
    update_user_meta($userObj->ID, 'lus_title',            isset($userLdap['title'])           ? $userLdap['title'] : '');
    update_user_meta($userObj->ID, 'lus_employeenumber',   isset($userLdap['employeenumber'])  ? $userLdap['employeenumber'] : '');
    update_user_meta($userObj->ID, 'lus_manager',          isset($userLdap['manager'])         ? $userLdap['manager'] : '');
    update_user_meta($userObj->ID, 'ldap_login', 'true');
}

Ldap\PostRequest::register('users_ldap', function () {
    #Verifica se o retorno é por URL ou Form
    $r_empresa = isset($_GET['empresa']) ? $_GET['empresa'] : $_POST['empresa'];
    
    $debug = true;
    $ldap = new Ldap\Config();
    $ad = new Adldap($ldap->configs[$r_empresa]);
    $ad->connect();

    $especiais_users = [];
    if (isset($ldap->configs[$r_empresa]['groups'])) {
        foreach ($ldap->configs[$r_empresa]['groups'] as $group) {
            $current_users = $ad->folder()->listing($group);
            $especiais_users = array_merge($especiais_users, $current_users);
        }
    }

    $users = $ad->user()->all();
    $users = array_merge($users, $especiais_users);
    if ($debug) {
        echo '<h1>' . $r_empresa . '</h1>';
        echo 'Total: ' . count($users) . '<br />';
    }
    $criados = 0;
    $atualizados = 0;
    $erros = 0;

    echo '
    <style>
    .pre {
        background: #eee;
        padding: 10px;
        max-height: 200px;
        margin-bottom: 10px;
        overflow: scroll;
    }
    </style>
    ';

    $usuarios_editados = [];

    global $wpdb;

    foreach ($users as $user) {
        if(isset($user['samaccountname'])){
            $name = isset($user['givenname']) ? $user['givenname'] : '';;
            $surname = isset($user['sn']) ? $user['sn'] : '';
            $email = isset($user['mail']) && !empty($user['mail']) ? strtolower($user['mail']) : false;
            #echo $email;
            if ($r_empresa == 'gps-pamcary') {
                $username = $user['samaccountname'];
            } else {
                $username = $user['samaccountname'] . '.' . $r_empresa;
            }

            if (!empty($email) && !empty($username)) {
                #Converte campos de nome e username
                if (function_exists('mb_convert_case')) {
                    $name = mb_convert_case(strtolower($name), MB_CASE_TITLE, 'UTF-8');
                    $surname = mb_convert_case(strtolower($surname), MB_CASE_TITLE, 'UTF-8');
                }

                #Verifica usuário por email - alterado para buscar pelo usuario
                $user_obj = get_user_by('login', $username);

                #Verifica se o login não existe no WP
                if ($user_obj) {
                    #Atualiza o email caso o login exista
                    $user_id = wp_update_user(
                        array(
                            'ID' => $user_obj->ID,
                            'user_email' => $email,
                        )
                    );

                    #Atualiza o usur_login do usuário
                    if ($user_obj->user_login != $user['samaccountname']) {
                        #Verifica se o nome de usuário já existe
                        #Se existir / Ele irá mudar o nome do outro, para o usuário ficar o correto
                        $user_existente = get_user_by('login', $user_obj->user_login);
                        if ($user_existente) {
                            $wpdb->update(
                                $wpdb->users,
                                array(
                                    'user_login' => $user_existente->user_login . '-outro'
                                ),
                                array('ID' => $user_existente->ID)
                            );
                        }
                        #Atualiza o user_login do usuário correto
                        $wpdb->update(
                            $wpdb->users,
                            array(
                                'user_login' => $user['samaccountname']
                            ),
                            array('ID' => $user_id)
                        );
                        #echo 'User errado';
                        #echo '<pre class="pre">';var_dump($user_obj->user_login, $user['samaccountname'], $email, $user_obj->ID);echo '</pre>';
                        #echo '<pre class="pre">';var_dump($user_obj);echo '</pre>';
                    }

                    #Verifica se deu erro
                    if (is_wp_error($user_id)) {
                        $erros++;
                    } else {
                        $atualizados++;
                    }
                } else {
                    #Cria o usuário caso o login não exista
                    $user_id = wp_insert_user([
                        'user_pass' => wp_generate_password(12, true, true),
                        'user_login' => $username,
                        'user_nicename' => $email,
                        'user_email' => $email,
                        'display_name' => $name,
                        'nickname' => $name,
                        'first_name' => $name,
                        'last_name' => $surname,
                        'user_registered' => date("Y-m-d H:i:s")
                    ]);
                    #Caso de algum erro de adiionar o usuário
                    if (is_wp_error($user_id)) {
                        $erros++;
                    } else {
                        $criados++;
                    }
                }

                $active = 1;
                foreach ($user['dn_array'] as $dn) {
                    if ($dn == 'BLOQUEADOS') {
                        $active = 0;
                    }
                    else
                    if ($dn == 'EXTERNOS') {
                        $active = 0;
                    }
                }

                #Caso o campo de ramal esteja vazio, ele desativa o usuário
                if (!isset($user['pager']) || empty($user['pager']) || $user['useraccountcontrol'] == "513" || $user['useraccountcontrol'] == "514") {
                    $active = 0;
                }
                update_user_meta($user_id, 'lus_active',           $active);
                update_user_meta($user_id, 'lus_name',             isset($user['cn'])              ? $user['cn'] : '');
                update_user_meta($user_id, 'lus_telephonenumber',  isset($user['telephonenumber']) ? $user['telephonenumber'] : '');
                update_user_meta($user_id, 'lus_pager',            isset($user['pager'])           ? $user['pager'] : '');
                update_user_meta($user_id, 'lus_st',               isset($user['st'])              ? $user['st'] : '');
                update_user_meta($user_id, 'lus_department',       isset($user['department'])      ? $user['department'] : '');
                update_user_meta($user_id, 'lus_department_floor', '');
                update_user_meta($user_id, 'lus_streetaddress',    isset($user['streetaddress'])   ? $user['streetaddress'] : '');
                update_user_meta($user_id, 'lus_houseidentifier',  isset($user['houseidentifier']) ? $user['houseidentifier'] : '');
                update_user_meta($user_id, 'lus_l',                isset($user['l'])               ? $user['l'] : '');
                update_user_meta($user_id, 'lus_title',            isset($user['title'])           ? $user['title'] : '');
                update_user_meta($user_id, 'lus_employeenumber',   isset($user['employeenumber'])  ? $user['employeenumber'] : '');
                update_user_meta($user_id, 'lus_manager',          isset($user['manager'])         ? $user['manager'] : '');
                update_user_meta($user_id, 'company',              $r_empresa);
                update_user_meta($user_id, 'ldap_login', 'true');
            } else {
                $msg = 'Usuário: <b>' . $user['samaccountname'] . '</b> sem email cadastrado no AD.';
                #echo '<pre class="pre">'.$msg.'</pre>';
                $erros++;
            }
        } else {
            $erros++;
        }
    }

    if ($debug) {
        echo 'Total Criados: ' . $criados . '<br />';
        echo 'Total Atualizados: ' . $atualizados . '<br />';
        echo 'Total Erros (Sem email): ' . $erros . '<br /><br />';
    }

    if ($debug) {
        exit;
    }

    wp_redirect($_SERVER['HTTP_REFERER']);
}, []);