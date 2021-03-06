<?php namespace Xitara\Core;

use App;
use Backend;
use BackendMenu;
use Config;
use Event;
use Html;
use Redirect;
use Storage;
use Str;
use System\Classes\PluginBase;
use System\Classes\PluginManager;
use Xitara\Core\Models\Config as CoreConfig;
use Xitara\Core\Models\CustomMenu;
use Xitara\Core\Models\Menu;

class Plugin extends PluginBase
{
    public $require = [
        'Romanov.ClearCacheWidget',
    ];

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name' => 'xitara.core::lang.plugin.name',
            'description' => 'xitara.core::lang.plugin.description',
            'author' => 'xitara.core::lang.plugin.author',
            'homepage' => 'xitara.core::lang.plugin.homepage',
            'icon' => '',
            'iconSvg' => 'plugins/xitara/core/assets/images/icon-core.svg',
        ];
    }

    public function register()
    {
        BackendMenu::registerContextSidenavPartial(
            'Xitara.Core',
            'core',
            '$/xitara/core/partials/_sidebar.htm'
        );

        $this->registerConsoleCommand('xitara.fakeblog', 'Xitara\Core\Console\FakeBlog');
        $this->registerConsoleCommand('xitara.fakeuser', 'Xitara\Core\Console\FakeUser');
    }

    public function boot()
    {
        // Check if we are currently in backend module.
        if (!App::runningInBackend()) {
            return;
        }

        /**
         * set new backend-skin
         */
        Config::set('cms.backendSkin', 'Xitara\Core\Classes\BackendSkin');

        /**
         * add items to sidemenu
         */
        $this->getSideMenu('Xitara.Core', 'core');

        Event::listen('backend.page.beforeDisplay', function ($controller, $action, $params) {
            if (CoreConfig::get('compact_display')) {
                $controller->addCss('/plugins/xitara/core/assets/css/compact.css');
            }

            $controller->addCss('/plugins/xitara/core/assets/css/backend.css');
            $controller->addCss('/plugins/xitara/core/assets/css/app.css');
            $controller->addJs('/plugins/xitara/core/assets/js/app.js');

            if ($controller instanceof Backend\Controllers\Index) {
                return Redirect::to('/backend/xitara/core/dashboard');
            }
        });

        /**
         * remove original dashboard
         */
        Event::listen('backend.menu.extendItems', function ($navigationManager) {
            $navigationManager->removeMainMenuItem('October.Backend', 'dashboard');
        });
    }

    public function registerSettings()
    {
        if (($category = CoreConfig::get('menu_text')) == '') {
            $category = 'xitara.core::core.config.name';
        }

        return [
            'configs' => [
                'category' => $category,
                'label' => 'xitara.core::lang.config.label',
                'description' => 'xitara.core::lang.config.description',
                'icon' => 'icon-wrench',
                'class' => 'Xitara\Core\Models\Config',
                'order' => 0,
                'permissions' => ['xitara.core.config'],
            ],
        ];
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'xitara.core.config' => [
                'tab' => 'Xitara Core',
                'label' => 'xitara.core::permissions.config',
            ],
            'xitara.core.dashboard' => [
                'tab' => 'Xitara Core',
                'label' => 'xitara.core::permissions.dashboard',
            ],
            'xitara.core.menu' => [
                'tab' => 'Xitara Core',
                'label' => 'xitara.core::permissions.menu',
            ],
            'xitara.core.custommenus' => [
                'tab' => 'Xitara Core',
                'label' => 'xitara.core::permissions.custommenus',
            ],
        ];
    }

    /**
     * Registers back-end navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        $iconSvg = CoreConfig::get('menu_icon');
        if ($iconSvg == '' && CoreConfig::get('menu_icon_text', '') == '') {
            $iconSvg = 'plugins/xitara/core/assets/images/icon-core.svg';
        } elseif ($iconSvg != '') {
            $iconSvg = url(Config::get('cms.storage.media.path') . $iconSvg);
        }

        if (($label = CoreConfig::get('menu_text')) == '') {
            $label = 'xitara.core::lang.submenu.label';
        }

        return [
            'core' => [
                'label' => $label,
                'url' => Backend::url('xitara/core/dashboard'),
                'icon' => CoreConfig::get('menu_icon_text', 'icon-leaf'),
                'iconSvg' => $iconSvg,
                'permissions' => ['xitara.core.*'],
                'order' => 50,
            ],
        ];
    }

    /**
     * grab sidemenu items
     * $inject contains addidtional menu-items with the following strcture
     *
     * name = [
     *     group => [string],
     *     label => string|'placeholder', // placeholder only
     *     url => [string], (full backend url)
     *     icon => [string],
     *     'attributes' => [
     *         'target' => [string],
     *         'placeholder' => true|false, // placeholder after elm
     *         'keywords' => [string],
     *         'description' => [string],
     *         'group' => [string], // group the items and set the heading of group
     *     ],
     * ]
     *
     * name -> unique name
     * group -> name to sort menu items
     * label -> shown name in menu
     * url -> url relative to backend
     * icon -> icon left of label
     * attribures -> array (optional)
     *     target -> _blank|_self (optional)
     *     keywords -> only for searching (optional)
     *     description -> showed under label (optional)
     *
     * @autor   mburghammer
     * @date    2018-05-15T20:49:04+0100
     * @version 0.0.3
     * @since   0.0.1
     * @since   0.0.2 added groups
     * @since   0.0.3 added attributes
     * @param   string                   $owner
     * @param   string                   $code
     * @param   array                   $inject
     */
    public static function getSideMenu(string $owner, string $code)
    {
        // Log::debug(CoreConfig::get('menu_text'));
        if (($group = CoreConfig::get('menu_text')) == '') {
            $group = 'xitara.core::lang.submenu.label';
        }
        // Log::debug($group);

        $items = [
            'core.dashboard' => [
                'label' => 'xitara.core::lang.core.dashboard',
                'url' => Backend::url('xitara/core/dashboard'),
                'icon' => 'icon-dashboard',
                'order' => 1,
                'permissions' => ['xitara.core.dashboard'],
                'attributes' => [
                    'group' => $group,
                ],
            ],
            'core.menu' => [
                'label' => 'xitara.core::lang.core.menu',
                'url' => Backend::url('xitara/core/menu/reorder'),
                'icon' => 'icon-sort',
                'order' => 2,
                'permissions' => ['xitara.core.menu'],
                'attributes' => [
                    'group' => $group,
                ],
            ],
            'core.custommenus' => [
                'label' => 'xitara.core::lang.custommenu.label',
                'url' => Backend::url('xitara/core/custommenus'),
                'icon' => 'icon-link',
                'order' => 3,
                'permissions' => ['xitara.core.custommenus'],
                'attributes' => [
                    'group' => $group,
                ],
            ],
        ];

        foreach (PluginManager::instance()->getPlugins() as $name => $plugin) {
            $namespace = str_replace('.', '\\', $name) . '\Plugin';

            if (method_exists($namespace, 'injectSideMenu')) {
                $inject = $namespace::injectSideMenu();

                $items = array_merge($items, $inject);
            }
        }

        Event::listen('backend.menu.extendItems', function ($manager) use ($owner, $code, $items) {
            $manager->addSideMenuItems($owner, $code, $items);
        });
    }

    public static function getMenuOrder(String $code): int
    {
        $item = Menu::find($code);

        if ($item === null) {
            return 9999;
        }

        return $item->sort_order;
    }

    /**
     * inject into sidemenu
     * @autor   mburghammer
     * @date    2020-06-26T21:13:34+02:00
     *
     * @see Xitara\Core::getSideMenu
     * @return  array                   sidemenu-data
     */
    public static function injectSideMenu()
    {
        // Log::debug(__METHOD__);

        $custommenus = CustomMenu::where('is_submenu', 1)
            ->where('is_active', 1)
            ->get();

        $inject = [];
        foreach ($custommenus as $custommenu) {
            $count = 0;
            foreach ($custommenu->links as $text => $link) {
                if ($link['is_active'] == 1) {
                    $icon = $iconSvg = null;

                    if (isset($link['icon']) && $link['icon'] != '') {
                        $icon = $link['icon'];
                    }

                    if (isset($link['icon_image']) && $link['icon_image'] != '') {
                        $iconSvg = url(Config::get('cms.storage.media.path') . $link['icon_image']);
                    }

                    // Log::debug($icon);
                    // Log::debug($iconSvg);

                    $inject['custommenulist.' . $custommenu->slug . '.' . Str::slug($link['text'])] = [
                        'label' => $link['text'],
                        'url' => $link['link'],
                        'icon' => $icon ?? null,
                        'iconSvg' => $iconSvg,
                        'permissions' => ['submenu.custommenu.' . $custommenu->slug . '.'
                            . Str::slug($link['text'])],
                        'attributes' => [
                            'group' => 'xitara.custommenulist.' . $custommenu->slug,
                            'groupLabel' => $custommenu->name,
                            'target' => ($link['is_blank'] == 1) ? '_blank' : null,
                            'keywords' => $link['keywords'] ?? null,
                            'description' => $link['description'] ?? null,
                        ],
                        'order' => self::getMenuOrder('xitara.custommenulist.' . $custommenu->slug) + $count++,
                    ];
                }
            }
        }

        return $inject;
    }

    public function registerMarkupTags()
    {
        return [
            'filters' => [
                'phone_link' => [$this, 'filterPhoneLink'],
                'email_link' => [$this, 'filterEmailLink'],
                'mediadata' => [$this, 'filterMediaData'],
                'filesize' => [$this, 'filterFileSize'],
                'regex_replace' => [$this, 'filterRegexReplace'],
                'slug' => 'str_slug',
                'strip_html' => [$this, 'filterStripHtml'],
                'truncate_html' => [$this, 'filterTruncateHtml'],
            ],
            'functions' => [
                'uid' => [$this, 'functionGenerateUid'],
            ],
        ];
    }

    /**
     * adds link to given phone
     *
     * options: {
     *     'classes': 'class1 class2 classN',
     *     'text_before': '<strong>sample</strong>',
     *     'text_after': '<strong>sample</strong>',
     *     'hide_mail': true|false (hide mail-address in text or not)
     * }
     *
     * @param  string $text    text from twig
     * @param  array $options options from twig
     * @return string          complete link in html
     */
    public function filterPhoneLink($text, $options = null)
    {
        /**
         * process options
         */
        $textBefore = $options['text_before'] ?? '';
        $textAfter = $options['text_after'] ?? '';
        $classes = $options['classes'] ?? null;
        $hideNubmer = $options['hide_number'] ?? false;

        /**
         * generate link
         */
        $link = '<a';

        if ($classes !== null) {
            $link .= ' class="' . $classes . '"';
        }

        $link .= ' href="tel:';
        $link .= preg_replace('/\(0\)|[^0-9\+]|\s+/', '', $text) . '">';
        $link .= $textBefore;

        if ($hideNubmer === false) {
            $link .= $text;
        }

        $link .= $textAfter;
        $link .= '</a>';

        return $link;
    }

    /**
     * adds link to given email
     *
     * options: {
     *     'classes': 'class1 class2 classN',
     *     'text_before': '<strong>sample</strong>',
     *     'text_after': '<strong>sample</strong>',
     *     'hide_mail': true|false (hide mail-address in text or not)
     * }
     *
     * @param  string $text    text from twig
     * @param  array $options options from twig
     * @return string          complete link in html
     */
    public function filterEmailLink($text, $options = null)
    {
        /**
         * remove subject and body from mail if given
         */
        $parts = explode('?', $text);
        $mail = $parts[0];
        $query = isset($parts[1]) ? '?' . $parts[1] : '';

        /**
         * obfuscate mailaddresses
         * @var closure
         */
        // $o = function () use ($mail) {
        //     $str = '';
        //     $a = unpack("C*", $mail);

        //     foreach ($a as $b) {
        //         $str .= sprintf("%%%X", $b);
        //     }

        //     return $str;
        // };

        /**
         * process options
         */
        $textBefore = $options['text_before'] ?? '';
        $textAfter = $options['text_after'] ?? '';
        $classes = $options['classes'] ?? null;
        $hideMail = $options['hide_mail'] ?? false;

        /**
         * generate link
         */
        $link = '<a';

        if ($classes !== null) {
            $link .= ' class="' . $classes . '"';
        }

        $link .= ' href="mailto:' . Html::email($mail) . $query . '">';
        $link .= $textBefore;

        if ($hideMail === false) {
            $link .= $mail;
        }

        $link .= $textAfter;
        $link .= '</a>';

        return $link;
    }

    /**
     * mediadata filter
     *
     * file should be in storage/app/[path], where path-default is "media"
     * for the media-manager
     *
     * @param  string $media filename
     * @param  string $path  relativ path in storage/app
     * @return array|boolean        filedata or false if file not exists
     */
    public function filterMediaData($media, $path = 'media')
    {
        if ($media === null) {
            return false;
        }

        if (strpos(Storage::getMimetype($path . $media), '/')) {
            list($type, $art) = explode('/', Storage::getMimetype($path . $media));
        }

        $data = [
            'size' => Storage::size($path . $media),
            'mime_type' => Storage::getMimetype($path . $media),
            'type' => $type ?? null,
            'art' => $art ?? null,
        ];

        return $data;
    }

    /**
     * filesize filter
     *
     * returns filesize of given file
     *
     * @param  string $filename filename
     * @param  string $path      path relative to storage/app, default "media"
     * @return int|boolean           filesize in bytes or false if file not exists
     */
    public function filterFileSize($filename, $path = 'media')
    {
        $size = Storage::size($path . $filename);
        return $size;
    }

    public function filterRegexReplace($subject, $pattern, $replacement)
    {
        return preg_replace($pattern, $replacement, $subject);
    }

    public function filterStripHtml($text)
    {
        return Html::strip($text);
    }

    public function filterTruncateHtml($text, $lenght, $hint = '...')
    {
        return Html::limit($text, $lenght, $hint);
    }

    public function functionGenerateUid()
    {
        return uniqid();
    }
}
