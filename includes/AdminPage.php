<?php
namespace Ldap;

class AdminPage
{
    private $title;
    private $slug;
    private $view;
    private $parent;
    private $position;

    public function __construct(){
        $this->add('LDAP', function () {
            ob_start();
            include __DIR__ . '/../views/users.php';
            echo ob_get_clean();
        });
    }

    public function add($title, callable $view, $parent = null, $position = null)
    {
        $this->title = $title;
        $this->slug = sanitize_title($this->title);
        $this->view = $view;
        $this->parent = $parent;
        $this->position = $position;

        add_action( 'admin_menu', [$this, 'addMenuPage'] );

        return $this;
    }

    public function addMenuPage()
    {
        if (null !== $this->parent) {
            add_submenu_page(
                $this->parent->getMenuSlug(),
                $this->title,
                $this->title,
                'manage_options',
                $this->getMenuSlug(),
                $this->view
            );
            return;
        }

        add_menu_page(
            $this->title,
            $this->title,
            'manage_options',
            $this->getMenuSlug(),
            $this->view,
            'dashicons-tickets',
            $this->position
        );
    }

    public function getMenuSlug()
    {
        return 'admin-page/'.$this->slug.'.php';
    }
}