<?php

namespace JoliMardi\Menu\Components;
// Attention ! Le namespace doit correspondre à l'arborescence des sous-dossiers ! (norme PSR-4)

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Blade;


class Menu extends Component {


    private array $menu_links = [];

    /**
     * Create a new component instance.
     */


    // Items OR yaml file (à partir de menu-name)
    public function __construct(public array $items = [], public string $name = '', public int $level = 0) {

        $menuArray = [];

        // Si on passe des items, on les prends en priorité
        if (count($items) > 0) {
            $menuArray = $items;

            // Sinon on tente de charger le yaml
        } else {
            $locale = app()->getLocale();

            $baseFilename = empty($name) ? 'menu' : "menu-$name";
            $localeFile = "../config/{$baseFilename}-{$locale}.yml";
            $defaultFile = "../config/{$baseFilename}.yml";

            // On predn d'abord le fichier avec la langue s'il existe, sans langue sinon
            if (is_file($localeFile)) {
                $yamlFile = $localeFile;
            } elseif (is_file($defaultFile)) {
                $yamlFile = $defaultFile;
            } else {
                throw new \ErrorException("\JoliMardi\Menu : Aucun fichier de menu trouvé. Recherche effectuée pour '{$localeFile}' et '{$defaultFile}'. Ajouter l'attribut name=\"user\" pour charger les menus spécifiques ou exécuter \"php artisan vendor:publish --provider=JoliMardi\Menu\MenuServiceProvider\" pour ajouter un menu.yml d'exemple dans le dossier /config/");
            }
            $menuArray = Yaml::parseFile($yamlFilename);
        }

        foreach ($menuArray as $routename => $menu_item_data) {
            $this->menu_links[] = self::create_menu_link_form_array($routename, $menu_item_data, $level);
        }
    }


    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string {
        $html = "<ul class='menu level-$this->level'>";
        foreach ($this->menu_links as $menu_link) {
            $html .= $menu_link->render()->with($menu_link->data());
        }
        $html .= '</ul>';
        return $html;
    }


    public static function create_menu_link_form_array(string $routename, string|array $menu_item_data_array, int $level = 0): MenuLink {

        if (isset($menu_item_data_array['callback']) && is_callable($menu_item_data_array['callback'])) {
            $menu_item_data_array = call_user_func($menu_item_data_array['callback'], $menu_item_data_array);
        }

        $title = self::extract_title($routename, $menu_item_data_array);
        $href = self::extract_href($routename, $menu_item_data_array);
        $is_active = self::is_active($routename, $menu_item_data_array);
        $submenu_html = self::get_submenu_html($routename, $menu_item_data_array);
        $icon = self::extract_icon($routename, $menu_item_data_array);
        $icon_before = self::extract_icon_before($routename, $menu_item_data_array);
        $classes_array = self::extract_classes($routename, $menu_item_data_array);

        $menu_link = new MenuLink($href, $title);
        $menu_link->classes_array = $classes_array;
        $menu_link->active = $is_active;
        $menu_link->level = $level;
        $menu_link->submenu_html = $submenu_html;
        if (!empty($icon)) {
            $menu_link->icon = $icon;
        }
        if (!empty($icon_before)) {
            $menu_link->icon_before = $icon_before;
        }

        return $menu_link;
    }


    public static function extract_href(string $routename, string|array $menu_item_data_array): string {
        if (isset($menu_item_data_array['href'])) {
            return $menu_item_data_array['href'];
        }
        if (Route::has($routename)) {
            return route($routename);
        }
        throw new \ErrorException('MenuLink : $routename "' . $routename . '" not existing');
    }


    public static function extract_title(string $routename, string|array $menu_item_data_array): string {
        if (is_string($menu_item_data_array)) {
            return $menu_item_data_array;
        }
        if (isset($menu_item_data_array['title'])) {
            return $menu_item_data_array['title'];
        } else {
            throw new \ErrorException('MenuLink : Aucun $title pour le MenuLink "' . $routename . '"');
        }
    }


    public static function extract_icon(string $routename, string|array $menu_item_data_array): string {
        if (isset($menu_item_data_array['icon'])) {
            return $menu_item_data_array['icon'];
        }
        return '';
    }


    public static function extract_icon_before(string $routename, string|array $menu_item_data_array): string {
        if (isset($menu_item_data_array['icon-before'])) {
            return $menu_item_data_array['icon-before'];
        }
        return '';
    }


    public static function extract_classes(string $routename, string|array $menu_item_data_array): array {
        if (isset($menu_item_data_array['class'])) {
            if (is_array($menu_item_data_array['class'])) {
                return $menu_item_data_array['class'];
            }
            if (is_string($menu_item_data_array['class'])) {
                return [$menu_item_data_array['class']];
            }
        }
        return [];
    }


    public static function is_active(string $routename, string|array $menu_item_data_array): bool {

        if (Request::routeIs($routename)) {
            return true;
        }

        if (isset($menu_item_data_array['active-routes'])) {
            foreach ($menu_item_data_array['active-routes'] as $active_route) {
                if (Request::routeIs($active_route)) {
                    return true;
                }
            }
        }

        if (isset($menu_item_data_array['submenu'])) {
            foreach ($menu_item_data_array['submenu'] as $submenu_route => $submenu_data) {
                if (Request::routeIs($submenu_route)) {
                    return true;
                }
            }
        }

        return false;
    }


    public static function get_submenu_html(string $routename, string|array $menu_item_data_array, $current_menu_level = 0): string {
        if (isset($menu_item_data_array['submenu'])) {

            // @TODO : Changer les paramètres pour coller au nouveau construct
            $submenu = new self($menu_item_data_array['submenu'], '', $current_menu_level + 1);

            //return $submenu->render()->with($submenu->data());

            /*echo '<pre>';
            print_r($submenu);
            print_r($submenu->data());
            echo '</pre>';
            exit(0);
            return $submenu->render()->with($submenu->data());*/
            return Blade::renderComponent($submenu);
        }
        return '';
    }
}


class MenuLink extends Component {

    public bool $has_icon = false;
    public bool $has_icon_before = false;
    public string $icon = '';
    public string $icon_before = '';
    public bool $has_submenu = false;
    public string $submenu_html = '';
    public int $level = 0;
    public string $classes = '';


    public function __construct(
        public string $href,
        public string $title,
        public bool   $active = false,
        public array  $classes_array = [],
    ) {}


    public function render(): View|Closure|string {

        if ($this->active) {
            $this->classes_array[] = 'is-active';
        }
        if (isset($this->level)) {
            $this->classes_array[] = 'level-' . $this->level;
        }
        if (!empty($this->icon)) {
            $this->has_icon = true;
            $this->classes_array[] = 'has-icon';
        }
        if (!empty($this->icon_before)) {
            $this->has_icon_before = true;
            $this->classes_array[] = 'has-icon icon-before';
        }
        if (!empty($this->submenu_html)) {
            $this->classes_array[] = 'has-submenu';
        }

        $this->classes = implode(' ', $this->classes_array);

        return view('menu::components.menu-link');
    }
}
