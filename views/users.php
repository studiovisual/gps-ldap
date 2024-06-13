<form action="/wp-admin/admin-post.php" method="POST">
    <input type="hidden" name="action" value="users_ldap" />
    <br /><br />
    <h2>Sincronizar usuários no AD</h2>
    <small>As informações dos usuários serão atualizadas de acordo com o AD.</small><br /><br />
    <select name="empresa" style="padding: 2px 18px 1px;">
        <option value="telerisco">Telerisco</option>
        <option value="roadcard">Roadcard</option>
        <option value="gps-pamcary">gps-pamcary</option>
    </select> 
    <button type="submit" class="button button-primary button-large mt-3">Sincronizar</button>
</form>

<form action="/wp-admin/admin-post.php" method="POST">
    <input type="hidden" name="action" value="user_ldap" />
    <br /><br />
    <h2>Sincronizar usuário específico no AD</h2>
    <small>As informações dos usuários serão atualizadas de acordo com o AD.</small><br /><br />
    <strong>Email: </strong><br />
    <input type="email" name="email" class="form-control" placeholder="Exemplo: jaqueline.ribeiro@gps-pamcary.com.br" style="padding: 2px 18px 1px;" /> 
    <button type="submit" class="button button-primary button-large mt-3">Sincronizar</button>
</form>
