<?php
defined('ABSPATH') || exit;

class Nexage_Gate {
    private static $instance;
    private $options;

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public function __construct() {
        $raw = get_option('nexage_gate_options', []);
        $this->options = $this->sanitize_options($raw);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_nexage_gate_save', [$this, 'handle_save']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_head', [$this, 'bootstrap_head'], 1);
        add_action('wp_footer', [$this, 'footer_modal_container']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('template_redirect', [$this, 'handle_endpoints']);
        if (class_exists('WooCommerce')) $this->woo_hooks();
    }

    public static function defaults() {
        return [
            'min_age' => 18,
            'scope' => 'global',
            'method' => 'date',
            'cookie_enabled' => 1,
            'cookie_days' => 30,
            'logo_id' => 0,
            'colors' => [
                'text' => '#111111',
                'button_bg' => '#111111',
                'button_text' => '#ffffff',
                'background' => 'rgba(0,0,0,0.8)',
                'panel_bg' => '#ffffff'
            ],
            'styles' => [
                'overlay_opacity' => 0.8,
                'border_style' => 'solid',
                'border_color' => '#dddddd',
                'border_radius' => 8
            ],
            'responsive' => [
                'mobile_font_scale' => 0.95,
                'mobile_spacing_scale' => 0.9
            ],
            'texts' => [
                'headline' => 'Age Verification',
                'description' => 'You must be at least {age}+ to visit this site.',
                'yes_label' => 'Yes',
                'no_label' => 'No',
                'remember_label' => 'Remember',
                'confirm_label' => 'Confirm',
                'deny_label' => 'Leave',
                'date_day' => 'Day',
                'date_month' => 'Month',
                'date_year' => 'Year',
                'mobile_headline' => 'Age Check',
                'mobile_description' => 'You must be {age}+.',
                'blocked_headline' => 'Access Restricted',
                'blocked_description' => 'You must be {age}+ to view this content.',
                'blocked_button_label' => 'Go Home',
                'blocked_button_url' => 'https://www.google.com',
                'blocked_retry_label' => 'I entered my data incorrectly'
            ],
            'paths' => [],
            'woo_categories' => [],
            'assets_version' => time()
        ];
    }

    public static function ensure_asset_dirs() {
        $dist = NEXAGE_GATE_PATH . 'assets/dist';
        $src = NEXAGE_GATE_PATH . 'assets/src';
        if (!file_exists($src)) wp_mkdir_p($src);
        if (!file_exists($dist)) wp_mkdir_p($dist);
    }

    public function admin_menu() {
        add_menu_page('NEXAGE Gate', 'NEXAGE Gate', 'manage_options', 'nexage_gate', [$this, 'render_admin_page'], 'dashicons-shield-alt', 65);
    }

    public function register_settings() {
        register_setting('nexage_gate', 'nexage_gate_options', ['sanitize_callback' => [$this, 'sanitize_options']]);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;
        $tab_in = filter_input(INPUT_GET, 'tab');
        $tab = $tab_in !== null && $tab_in !== false ? sanitize_text_field($tab_in) : 'general';
        $o = $this->options;
        $categories = [];
        if (class_exists('WooCommerce')) {
            $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
            if (!is_wp_error($terms)) $categories = $terms;
        }
        wp_enqueue_media();
        echo '<div class="wrap">';
        echo '<h1>NEXAGE Gate</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        $tabs = ['general' => 'General Settings', 'text' => 'Text Customization', 'visual' => 'Visual Customization', 'restrictions' => 'Custom Restrictions'];
        foreach ($tabs as $key => $label) {
            $class = $tab === $key ? ' nav-tab nav-tab-active' : ' nav-tab';
            echo '<a href="' . esc_url(admin_url('admin.php?page=nexage_gate&tab=' . $key)) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="nexage_gate_save">';
        echo '<input type="hidden" name="tab" value="' . esc_attr($tab) . '">';
        wp_nonce_field('nexage_gate_save', 'nexage_gate_nonce');
        if ($tab === 'general') {
            echo '<table class="form-table">';
            echo '<tr><th>Default minimum age</th><td><input type="number" name="min_age" value="' . esc_attr($o['min_age']) . '" min="0"></td></tr>';
            echo '<tr><th>Restriction scope</th><td><select name="scope"><option value="global"' . selected($o['scope'], 'global', false) . '>Global</option><option value="custom"' . selected($o['scope'], 'custom', false) . '>Custom</option></select></td></tr>';
            echo '<tr><th>Validation method</th><td><select name="method"><option value="date"' . selected($o['method'], 'date', false) . '>Date entry</option><option value="yesno"' . selected($o['method'], 'yesno', false) . '>Yes/No</option></select></td></tr>';
            echo '<tr><th>Cookie duration</th><td><label><input type="checkbox" name="cookie_enabled" value="1"' . checked((int)$o['cookie_enabled'], 1, false) . '> Enable</label> <input type="number" name="cookie_days" value="' . esc_attr($o['cookie_days']) . '" min="1"> days</td></tr>';
            echo '</table>';
        }
        if ($tab === 'text') {
            $t = $o['texts'];
            $dt = self::defaults()['texts'];
            echo '<table class="form-table">';
            echo '<tr><th>Headline</th><td><input type="text" name="texts[headline]" value="' . esc_attr($t['headline']) . '" class="regular-text"></td></tr>';
            echo '<tr><th>Description</th><td><textarea name="texts[description]" class="large-text" rows="3">' . esc_textarea($t['description']) . '</textarea></td></tr>';
            echo '<tr><th>Mobile headline</th><td><input type="text" name="texts[mobile_headline]" value="' . esc_attr($t['mobile_headline']) . '" class="regular-text"></td></tr>';
            echo '<tr><th>Mobile description</th><td><textarea name="texts[mobile_description]" class="large-text" rows="3">' . esc_textarea($t['mobile_description']) . '</textarea></td></tr>';
            echo '<tr><th>Yes label</th><td><input type="text" name="texts[yes_label]" value="' . esc_attr($t['yes_label']) . '"></td></tr>';
            echo '<tr><th>No label</th><td><input type="text" name="texts[no_label]" value="' . esc_attr($t['no_label']) . '"></td></tr>';
            echo '<tr><th>Remember label</th><td><input type="text" name="texts[remember_label]" value="' . esc_attr($t['remember_label']) . '"></td></tr>';
            echo '<tr><th>Confirm label</th><td><input type="text" name="texts[confirm_label]" value="' . esc_attr($t['confirm_label']) . '"></td></tr>';
            echo '<tr><th>Deny label</th><td><input type="text" name="texts[deny_label]" value="' . esc_attr($t['deny_label']) . '"></td></tr>';
            echo '<tr><th>Date labels</th><td>';
            echo '<input type="text" name="texts[date_day]" value="' . esc_attr($t['date_day']) . '" placeholder="Day"> ';
            echo '<input type="text" name="texts[date_month]" value="' . esc_attr($t['date_month']) . '" placeholder="Month"> ';
            echo '<input type="text" name="texts[date_year]" value="' . esc_attr($t['date_year']) . '" placeholder="Year">';
            echo '</td></tr>';
            $bh = isset($t['blocked_headline']) && trim((string)$t['blocked_headline']) !== '' ? $t['blocked_headline'] : $dt['blocked_headline'];
            $bd = isset($t['blocked_description']) && trim((string)$t['blocked_description']) !== '' ? $t['blocked_description'] : $dt['blocked_description'];
            $bl = isset($t['blocked_button_label']) && trim((string)$t['blocked_button_label']) !== '' ? $t['blocked_button_label'] : $dt['blocked_button_label'];
            $bu = isset($t['blocked_button_url']) && trim((string)$t['blocked_button_url']) !== '' ? $t['blocked_button_url'] : $dt['blocked_button_url'];
            $br = isset($t['blocked_retry_label']) && trim((string)$t['blocked_retry_label']) !== '' ? $t['blocked_retry_label'] : $dt['blocked_retry_label'];
            echo '<tr><th>Blocked page headline</th><td><input type="text" name="texts[blocked_headline]" value="' . esc_attr($bh) . '" class="regular-text"></td></tr>';
            echo '<tr><th>Blocked page description</th><td><textarea name="texts[blocked_description]" class="large-text" rows="3">' . esc_textarea($bd) . '</textarea><p class="description">Use {age} to insert the required age.</p></td></tr>';
            echo '<tr><th>Blocked button label</th><td><input type="text" name="texts[blocked_button_label]" value="' . esc_attr($bl) . '"></td></tr>';
            echo '<tr><th>Blocked button URL</th><td><input type="text" name="texts[blocked_button_url]" value="' . esc_attr($bu) . '" class="regular-text"><p class="description">Absolute URL or relative path (e.g., /shop). Defaults to https://www.google.com.</p></td></tr>';
            echo '<tr><th>Retry button label</th><td><input type="text" name="texts[blocked_retry_label]" value="' . esc_attr($br) . '"></td></tr>';
            echo '</table>';
        }
        if ($tab === 'visual') {
            $c = $o['colors'];
            $s = $o['styles'];
            $r = $o['responsive'];
            echo '<table class="form-table">';
            echo '<tr><th>Logo</th><td>';
            $logo_id = (int)$o['logo_id'];
            $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
            echo '<div id="nexage-logo-preview">' . ($logo_url ? '<img src="' . esc_url($logo_url) . '" style="max-width:120px;height:auto;">' : '') . '</div>';
            echo '<input type="hidden" id="nexage-logo-id" name="logo_id" value="' . esc_attr($logo_id) . '">';
            echo '<button type="button" class="button" id="nexage-logo-select">Select Logo</button>';
            echo '</td></tr>';
            echo '<tr><th>Text color</th><td><input type="text" name="colors[text]" value="' . esc_attr($c['text']) . '" class="regular-text"></td></tr>';
            echo '<tr><th>Button background</th><td><input type="text" name="colors[button_bg]" value="' . esc_attr($c['button_bg']) . '" class="regular-text"></td></tr>';
            echo '<tr><th>Button text</th><td><input type="text" name="colors[button_text]" value="' . esc_attr($c['button_text']) . '" class="regular-text"></td></tr>';
            echo '<tr><th>Overlay background</th><td><input type="text" name="colors[background]" value="' . esc_attr($c['background']) . '" class="regular-text"></td></tr>';
            echo '<tr><th>Panel background</th><td><input type="text" name="colors[panel_bg]" value="' . esc_attr($c['panel_bg']) . '" class="regular-text"></td></tr>';
            echo '<tr><th>Overlay opacity</th><td><input type="number" step="0.05" min="0" max="1" name="styles[overlay_opacity]" value="' . esc_attr($s['overlay_opacity']) . '"></td></tr>';
            echo '<tr><th>Border style</th><td><select name="styles[border_style]"><option value="none"' . selected($s['border_style'], 'none', false) . '>None</option><option value="solid"' . selected($s['border_style'], 'solid', false) . '>Solid</option><option value="dashed"' . selected($s['border_style'], 'dashed', false) . '>Dashed</option><option value="double"' . selected($s['border_style'], 'double', false) . '>Double</option></select></td></tr>';
            echo '<tr><th>Border color</th><td><input type="text" name="styles[border_color]" value="' . esc_attr($s['border_color']) . '" class="regular-text"></td></tr>';
            echo '<tr><th>Border radius</th><td><input type="number" name="styles[border_radius]" value="' . esc_attr($s['border_radius']) . '" min="0"></td></tr>';
            echo '<tr><th>Mobile font scale</th><td><input type="number" step="0.05" name="responsive[mobile_font_scale]" value="' . esc_attr($r['mobile_font_scale']) . '"></td></tr>';
            echo '<tr><th>Mobile spacing scale</th><td><input type="number" step="0.05" name="responsive[mobile_spacing_scale]" value="' . esc_attr($r['mobile_spacing_scale']) . '"></td></tr>';
            echo '</table>';
            echo '<script>(function(){var btn=document.getElementById("nexage-logo-select");if(!btn)return;btn.addEventListener("click",function(){var frame=wp.media({title:"Select Logo",multiple:false});frame.on("select",function(){var a=frame.state().get("selection").first().toJSON();document.getElementById("nexage-logo-id").value=a.id;var p=document.getElementById("nexage-logo-preview");if(p){p.innerHTML="";var img=new Image();img.alt="";img.style.maxWidth="120px";img.style.height="auto";img.src=a.url;p.appendChild(img);}});frame.open();});})();</script>';
        }
        if ($tab === 'restrictions') {
            echo '<table class="form-table">';
            echo '<tr><th>Path-based restrictions</th><td><textarea name="paths" class="large-text" rows="6">' . esc_textarea(implode("\n", array_map('sanitize_text_field', (array)$o['paths']))) . '</textarea><p>One path per line</p></td></tr>';
            if ($categories) {
                echo '<tr><th>WooCommerce categories</th><td>';
                $selected = (array)$o['woo_categories'];
                foreach ($categories as $cat) {
                    $is_checked = in_array((string)$cat->term_id, array_map('strval', $selected), true);
                    echo '<label style="display:block;margin:4px 0;"><input type="checkbox" name="woo_categories[]" value="' . esc_attr($cat->term_id) . '" ' . checked($is_checked, true, false) . '> ' . esc_html($cat->name) . '</label>';
                }
                echo '</td></tr>';
            }
            echo '</table>';
        }
        submit_button('Save Changes');
        echo '</form>';
        echo '</div>';
    }

    public function handle_save() {
        if (!current_user_can('manage_options')) wp_die('');
        $nonce = isset($_POST['nexage_gate_nonce']) ? sanitize_text_field(wp_unslash($_POST['nexage_gate_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'nexage_gate_save')) wp_die('');
        $incoming = $_POST;
        $opts = $this->options;
        $tab = isset($_POST['tab']) ? sanitize_text_field(wp_unslash($_POST['tab'])) : 'general';
        if ($tab === 'general') {
            $opts['min_age'] = isset($incoming['min_age']) ? max(0, (int)$incoming['min_age']) : $opts['min_age'];
            $scope = isset($incoming['scope']) ? sanitize_text_field($incoming['scope']) : $opts['scope'];
            $opts['scope'] = in_array($scope, ['global','custom'], true) ? $scope : 'global';
            $method = isset($incoming['method']) ? sanitize_text_field($incoming['method']) : $opts['method'];
            $opts['method'] = in_array($method, ['date','yesno'], true) ? $method : 'date';
            $opts['cookie_enabled'] = isset($incoming['cookie_enabled']) ? 1 : 0;
            $opts['cookie_days'] = isset($incoming['cookie_days']) ? max(1, (int)$incoming['cookie_days']) : $opts['cookie_days'];
        }
        if ($tab === 'text') {
            $t = isset($incoming['texts']) ? (array)$incoming['texts'] : [];
            $opts['texts']['headline'] = isset($t['headline']) ? sanitize_text_field($t['headline']) : $opts['texts']['headline'];
            $opts['texts']['description'] = isset($t['description']) ? wp_kses_post($t['description']) : $opts['texts']['description'];
            $opts['texts']['mobile_headline'] = isset($t['mobile_headline']) ? sanitize_text_field($t['mobile_headline']) : $opts['texts']['mobile_headline'];
            $opts['texts']['mobile_description'] = isset($t['mobile_description']) ? wp_kses_post($t['mobile_description']) : $opts['texts']['mobile_description'];
            foreach (['yes_label','no_label','remember_label','confirm_label','deny_label','date_day','date_month','date_year'] as $k) {
                $opts['texts'][$k] = isset($t[$k]) ? sanitize_text_field($t[$k]) : $opts['texts'][$k];
            }
            $opts['texts']['blocked_headline'] = isset($t['blocked_headline']) ? sanitize_text_field($t['blocked_headline']) : $opts['texts']['blocked_headline'];
            $opts['texts']['blocked_description'] = isset($t['blocked_description']) ? wp_kses_post($t['blocked_description']) : $opts['texts']['blocked_description'];
            $opts['texts']['blocked_button_label'] = isset($t['blocked_button_label']) ? sanitize_text_field($t['blocked_button_label']) : $opts['texts']['blocked_button_label'];
            $opts['texts']['blocked_button_url'] = isset($t['blocked_button_url']) ? sanitize_text_field($t['blocked_button_url']) : $opts['texts']['blocked_button_url'];
            $opts['texts']['blocked_retry_label'] = isset($t['blocked_retry_label']) ? sanitize_text_field($t['blocked_retry_label']) : $opts['texts']['blocked_retry_label'];
        }
        if ($tab === 'visual') {
            $opts['logo_id'] = isset($incoming['logo_id']) ? (int)$incoming['logo_id'] : 0;
            $c = isset($incoming['colors']) ? (array)$incoming['colors'] : [];
            foreach (['text','button_bg','button_text','background','panel_bg'] as $k) {
                $opts['colors'][$k] = isset($c[$k]) ? sanitize_text_field($c[$k]) : $opts['colors'][$k];
            }
            $s = isset($incoming['styles']) ? (array)$incoming['styles'] : [];
            $opts['styles']['overlay_opacity'] = isset($s['overlay_opacity']) ? max(0, min(1, (float)$s['overlay_opacity'])) : $opts['styles']['overlay_opacity'];
            $opts['styles']['border_style'] = isset($s['border_style']) ? sanitize_text_field($s['border_style']) : $opts['styles']['border_style'];
            $opts['styles']['border_color'] = isset($s['border_color']) ? sanitize_text_field($s['border_color']) : $opts['styles']['border_color'];
            $opts['styles']['border_radius'] = isset($s['border_radius']) ? max(0, (int)$s['border_radius']) : $opts['styles']['border_radius'];
            $r = isset($incoming['responsive']) ? (array)$incoming['responsive'] : [];
            $opts['responsive']['mobile_font_scale'] = isset($r['mobile_font_scale']) ? max(0.5, min(2.0, (float)$r['mobile_font_scale'])) : $opts['responsive']['mobile_font_scale'];
            $opts['responsive']['mobile_spacing_scale'] = isset($r['mobile_spacing_scale']) ? max(0.5, min(2.0, (float)$r['mobile_spacing_scale'])) : $opts['responsive']['mobile_spacing_scale'];
        }
        if ($tab === 'restrictions') {
            $paths = isset($incoming['paths']) ? explode("\n", str_replace("\r", '', (string)$incoming['paths'])) : [];
            $clean = [];
            foreach ($paths as $p) {
                $p = trim($p);
                if ($p !== '') $clean[] = sanitize_text_field($p);
            }
            $opts['paths'] = $clean;
            $cats = isset($incoming['woo_categories']) ? (array)$incoming['woo_categories'] : [];
            $opts['woo_categories'] = array_values(array_filter(array_map('intval', $cats), function($v){return $v>0;}));
        }
        update_option('nexage_gate_options', $opts);
        self::regenerate_minified_assets();
        $opts['assets_version'] = time();
        update_option('nexage_gate_options', $opts);
        $ref = admin_url('admin.php?page=nexage_gate&tab=' . $tab);
        wp_safe_redirect($ref);
        exit;
    }

    public function sanitize_options($opts) {
        $defaults = self::defaults();
        if (!is_array($opts)) $opts = [];
        $out = wp_parse_args($opts, $defaults);
        $out['texts'] = wp_parse_args(isset($opts['texts']) ? (array)$opts['texts'] : [], $defaults['texts']);
        $out['min_age'] = max(0, (int)$out['min_age']);
        $out['scope'] = in_array($out['scope'], ['global','custom'], true) ? $out['scope'] : 'global';
        $out['method'] = in_array($out['method'], ['date','yesno'], true) ? $out['method'] : 'date';
        $out['cookie_enabled'] = (int)!!$out['cookie_enabled'];
        $out['cookie_days'] = max(1, (int)$out['cookie_days']);
        $out['logo_id'] = (int)$out['logo_id'];
        $out['styles']['overlay_opacity'] = max(0, min(1, (float)$out['styles']['overlay_opacity']));
        $out['styles']['border_style'] = sanitize_text_field($out['styles']['border_style']);
        $out['styles']['border_color'] = sanitize_text_field($out['styles']['border_color']);
        $out['styles']['border_radius'] = max(0, (int)$out['styles']['border_radius']);
        $out['responsive']['mobile_font_scale'] = max(0.5, min(2.0, (float)$out['responsive']['mobile_font_scale']));
        $out['responsive']['mobile_spacing_scale'] = max(0.5, min(2.0, (float)$out['responsive']['mobile_spacing_scale']));
        $paths = isset($out['paths']) ? (array)$out['paths'] : [];
        $clean = [];
        foreach ($paths as $p) {
            $p = trim((string)$p);
            if ($p !== '') $clean[] = sanitize_text_field($p);
        }
        $out['paths'] = $clean;
        $cats = isset($out['woo_categories']) ? (array)$out['woo_categories'] : [];
        $out['woo_categories'] = array_values(array_filter(array_map('intval', $cats), function($v){return $v>0;}));
        return $out;
    }

    public function enqueue_assets() {
        $ver = isset($this->options['assets_version']) ? (string)$this->options['assets_version'] : (string)time();
        $css = file_exists(NEXAGE_GATE_PATH . 'assets/dist/modal.min.css') ? 'assets/dist/modal.min.css' : 'assets/src/modal.css';
        $js = file_exists(NEXAGE_GATE_PATH . 'assets/dist/modal.min.js') ? 'assets/dist/modal.min.js' : 'assets/src/modal.js';
        wp_register_style('nexage-gate', NEXAGE_GATE_URL . $css, [], $ver);
        wp_register_script('nexage-gate', NEXAGE_GATE_URL . $js, [], $ver, false);
        $vars = $this->script_vars();
        wp_localize_script('nexage-gate', 'nexageGate', $vars);
        wp_enqueue_style('nexage-gate');
        wp_enqueue_script('nexage-gate');
        $styles = ':root{--nexage-text:' . esc_attr($this->options['colors']['text']) . ';--nexage-button-bg:' . esc_attr($this->options['colors']['button_bg']) . ';--nexage-button-text:' . esc_attr($this->options['colors']['button_text']) . ';--nexage-overlay:' . esc_attr($this->options['colors']['background']) . ';--nexage-panel-bg:' . esc_attr($this->options['colors']['panel_bg']) . ';--nexage-radius:' . (int)$this->options['styles']['border_radius'] . 'px;--nexage-border-color:' . esc_attr($this->options['styles']['border_color']) . ';--nexage-border-style:' . esc_attr($this->options['styles']['border_style']) . ';--nexage-mobile-font-scale:' . esc_attr($this->options['responsive']['mobile_font_scale']) . ';--nexage-mobile-spacing-scale:' . esc_attr($this->options['responsive']['mobile_spacing_scale']) . ';}';
        wp_add_inline_style('nexage-gate', $styles);
    }

    private function script_vars() {
        $is_restricted = $this->is_restricted_request();
        $logo = (int)$this->options['logo_id'] ? wp_get_attachment_image_url((int)$this->options['logo_id'], 'medium') : '';
        return [
            'minAge' => (int)$this->options['min_age'],
            'method' => (string)$this->options['method'],
            'cookieEnabled' => (bool)$this->options['cookie_enabled'],
            'cookieDays' => (int)$this->options['cookie_days'],
            'needsGate' => (bool)$is_restricted,
            'texts' => $this->options['texts'],
            'logo' => $logo,
            'blockedUrl' => home_url('/?nexage_gate_blocked=1'),
            'svgUrl' => home_url('/?nexage_gate_svg=1&age=' . (int)$this->options['min_age']),
            'overlayOpacity' => (float)$this->options['styles']['overlay_opacity']
        ];
    }

    public function bootstrap_head() {
        $needs = $this->is_restricted_request();
        if (!$needs) return;
        $cookie = isset($_COOKIE['nexage_gate_access']) ? sanitize_text_field(wp_unslash($_COOKIE['nexage_gate_access'])) : '';
        if ($cookie === 'approved') return;
        echo '<style id="nexage-gate-block">html.nexage-gate-hidden body{overflow:hidden}html.nexage-gate-hidden .nexage-gate-hide{visibility:hidden}</style>';
        echo '<script>!function(){if(document.cookie.indexOf("nexage_gate_access=approved")>-1)return;document.documentElement.classList.add("nexage-gate-hidden");}();</script>';
    }

    public function footer_modal_container() {
        $needs = $this->is_restricted_request();
        if (!$needs) return;
        $cookie = isset($_COOKIE['nexage_gate_access']) ? sanitize_text_field(wp_unslash($_COOKIE['nexage_gate_access'])) : '';
        if ($cookie === 'approved') return;
        echo '<div id="nexage-gate-root"></div>';
    }

    public function register_query_vars($vars) {
        $vars[] = 'nexage_gate_svg';
        $vars[] = 'nexage_gate_blocked';
        $vars[] = 'nexage_gate_retry';
        return $vars;
    }

    public function handle_endpoints() {
        $svg = get_query_var('nexage_gate_svg');
        $blocked = get_query_var('nexage_gate_blocked');
        $retry = get_query_var('nexage_gate_retry');
        if ($retry) {
            status_header(200);
            nocache_headers();
            setcookie('nexage_gate_access', '', time() - 3600, '/');
            $home = home_url('/');
            echo '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Retry</title></head><body><script>document.cookie="nexage_gate_access=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax";location.replace(' . json_encode($home) . ');</script></body></html>';
            exit;
        }
        if ($svg) {
            $age_in = filter_input(INPUT_GET, 'age', FILTER_SANITIZE_NUMBER_INT);
            $age = $age_in !== null && $age_in !== false ? max(0, (int)$age_in) : (int)$this->options['min_age'];
            header('Content-Type: image/svg+xml');
            $svg = $this->render_svg($age);
            echo wp_kses($svg, $this->allowed_svg_tags());
            exit;
        }
        if ($blocked) {
            status_header(403);
            nocache_headers();
            $age = (int)$this->options['min_age'];
            $t = isset($this->options['texts']) ? (array)$this->options['texts'] : [];
            $headline = isset($t['blocked_headline']) && $t['blocked_headline'] !== '' ? $t['blocked_headline'] : 'Access Restricted';
            $desc = isset($t['blocked_description']) && $t['blocked_description'] !== '' ? $t['blocked_description'] : 'You must be at least {age}+ to view this content.';
            $desc = str_replace('{age}', (string)$age, $desc);
            $btn_label = isset($t['blocked_button_label']) && $t['blocked_button_label'] !== '' ? $t['blocked_button_label'] : 'Go Home';
            $btn_raw = isset($t['blocked_button_url']) ? trim((string)$t['blocked_button_url']) : '';
            $btn_url = $btn_raw !== '' ? ( (stripos($btn_raw, 'http://') === 0 || stripos($btn_raw, 'https://') === 0 || strpos($btn_raw, '//') === 0) ? $btn_raw : home_url($btn_raw) ) : home_url('/');
            $retry_label = isset($t['blocked_retry_label']) && $t['blocked_retry_label'] !== '' ? $t['blocked_retry_label'] : 'I entered my data incorrectly';
            $btn_bg = isset($this->options['colors']['button_bg']) ? (string)$this->options['colors']['button_bg'] : '#111';
            $btn_text = isset($this->options['colors']['button_text']) ? (string)$this->options['colors']['button_text'] : '#fff';
            echo '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html($headline) . '</title><style>body{margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#111;color:#fff;font-family:system-ui,Segoe UI,Roboto,Arial} .wrap{text-align:center;padding:24px} img{max-width:200px;height:auto;display:block;margin:0 auto 16px} a.button{display:inline-block;background:' . esc_attr($btn_bg) . ';color:' . esc_attr($btn_text) . ';padding:10px 16px;text-decoration:none;border-radius:6px;margin:0 6px} </style></head><body><div class="wrap"><img src="' . esc_url(home_url('/?nexage_gate_svg=1&age=' . $age)) . '" alt="+' . esc_attr($age) . '"><h1>' . esc_html($headline) . '</h1><p>' . wp_kses_post($desc) . '</p><p><a class="button" href="' . esc_url($btn_url) . '">' . esc_html($btn_label) . '</a><a class="button" href="' . esc_url(home_url('/?nexage_gate_retry=1')) . '">' . esc_html($retry_label) . '</a></p></div></body></html>';
            exit;
        }
        $cookie = isset($_COOKIE['nexage_gate_access']) ? sanitize_text_field(wp_unslash($_COOKIE['nexage_gate_access'])) : '';
        if ($cookie === 'denied' && $this->is_restricted_request()) {
            wp_safe_redirect(home_url('/?nexage_gate_blocked=1'));
            exit;
        }
    }

    private function render_svg($age) {
        $bg = '#111';
        $fg = '#fff';
        $panel = '#fff';
        $text = '#111';
        $panel = isset($this->options['colors']['panel_bg']) ? $this->options['colors']['panel_bg'] : $panel;
        $text = isset($this->options['colors']['text']) ? $this->options['colors']['text'] : $text;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><rect width="512" height="512" fill="' . esc_attr($panel) . '"/><circle cx="256" cy="256" r="200" fill="none" stroke="' . esc_attr($text) . '" stroke-width="12"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Segoe UI, Roboto, Arial" font-size="160" fill="' . esc_attr($text) . '">+' . (int)$age . '</text></svg>';
        return $svg;
    }

    private function is_restricted_request() {
        $scope = $this->options['scope'];
        if ($scope === 'global') return true;
        $paths = (array)$this->options['paths'];
        $req = trim((string)wp_parse_url(add_query_arg([]), PHP_URL_PATH), '/');
        foreach ($paths as $p) {
            $p = trim($p, '/');
            if ($p === '') continue;
            if (substr($p, -1) === '*') {
                $base = rtrim(substr($p, 0, -1), '/');
                if (strpos($req, $base) === 0) return true;
            } else {
                if ($req === $p) return true;
            }
        }
        if (class_exists('WooCommerce')) {
            if (function_exists('is_product') && is_product()) {
                $pid = get_the_ID();
                $cats = (array)$this->options['woo_categories'];
                if ($cats && has_term($cats, 'product_cat', $pid)) return true;
            }
            if (function_exists('is_product_category') && is_product_category()) {
                $q = get_queried_object();
                if ($q && in_array((int)$q->term_id, array_map('intval', (array)$this->options['woo_categories']), true)) return true;
            }
        }
        return false;
    }

    private function woo_hooks() {
        add_filter('woocommerce_product_get_image', [$this, 'woo_replace_product_image'], 10, 5);
    }

    public function woo_replace_product_image($image, $product, $size, $attr, $placeholder) {
        $cats = (array)$this->options['woo_categories'];
        if (!$cats) return $image;
        $cookie = isset($_COOKIE['nexage_gate_access']) ? sanitize_text_field(wp_unslash($_COOKIE['nexage_gate_access'])) : '';
        if ($cookie === 'approved') return $image;
        $id = $product instanceof WC_Product ? $product->get_id() : 0;
        if ($id && has_term($cats, 'product_cat', $id)) {
            $url = home_url('/?nexage_gate_svg=1&age=' . (int)$this->options['min_age']);
            $alt = '+' . (int)$this->options['min_age'];
            return '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" class="nexage-gate-svg-replace">';
        }
        return $image;
    }

    public static function regenerate_minified_assets() {
        self::ensure_asset_dirs();
        $css_src = NEXAGE_GATE_PATH . 'assets/src/modal.css';
        $js_src = NEXAGE_GATE_PATH . 'assets/src/modal.js';
        $css_out = NEXAGE_GATE_PATH . 'assets/dist/modal.min.css';
        $js_out = NEXAGE_GATE_PATH . 'assets/dist/modal.min.js';
        if (file_exists($css_src)) {
            $css = file_get_contents($css_src);
            if ($css !== false) {
                $css = preg_replace('!/\*.*?\*/!s', '', $css);
                $css = preg_replace('/\s+/', ' ', $css);
                file_put_contents($css_out, trim($css));
            }
        }
        if (file_exists($js_src)) {
            $js = file_get_contents($js_src);
            if ($js !== false) {
                $js = preg_replace('!^\s*//.*$!m', "\n", $js);
                $js = preg_replace('!/\*.*?\*/!s', '', $js);
                $js = preg_replace('/\s+/', ' ', $js);
                file_put_contents($js_out, trim($js));
            }
        }
    }

    private function allowed_svg_tags() {
        return [
            'svg' => [
                'xmlns' => true,
                'width' => true,
                'height' => true,
                'viewbox' => true
            ],
            'rect' => [
                'width' => true,
                'height' => true,
                'fill' => true,
                'x' => true,
                'y' => true
            ],
            'circle' => [
                'cx' => true,
                'cy' => true,
                'r' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true
            ],
            'text' => [
                'x' => true,
                'y' => true,
                'dominant-baseline' => true,
                'text-anchor' => true,
                'font-family' => true,
                'font-size' => true,
                'fill' => true
            ]
        ];
    }
}
