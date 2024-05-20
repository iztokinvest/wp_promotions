<?php
/*
Plugin Name: Промоции
Description: Promo shortcodes.
Version: 1.0
Author: Martin Mladenov
*/

include_once(plugin_dir_path(__FILE__) . 'data/shortcodes.php');

function load_bootstrap() {
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), null, true);
}

function enqueue_promotions_script() {
    $script_version = filemtime(plugin_dir_path(__FILE__) . 'promotions.js');
    wp_register_script('promotions-script', plugin_dir_url(__FILE__) . 'promotions.js', array(), $script_version, true);
    wp_enqueue_script('promotions-script');
    wp_enqueue_style('vanillajs-datepicker-css', 'https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/css/datepicker.min.css');
    wp_enqueue_script('vanillajs-datepicker-js', 'https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/js/datepicker.min.js', array(), null, true);
}

function load_shortcode_template($file, $placeholders) {
    $full_path = plugin_dir_path(__FILE__) . $file;

    if (!file_exists($full_path)) {
        return '';
    }

    $content = file_get_contents($full_path);

    foreach ($placeholders as $key => $value) {
        $content = str_replace("[$key]", $value, $content);
    }

    return $content;
}

function shortcodes($image, $alt) {
    foreach (shortcode_names() as $key => $value) {
        $shortcodes[$key] = load_shortcode_template('data/templates/'.$key.'.php', ['image' => $image, 'alt' => $alt]);
    }

    return $shortcodes;
}

function handle_shortcode($atts, $shortcode) {
    global $wpdb, $product;

    $table_name = $wpdb->prefix . 'promotions';
    $current_date = date('Y-m-d');

    $product_categories = $product ? $product->get_category_ids() : [];

    $query = "SELECT * FROM $table_name WHERE shortcode = %s AND start_date <= %s AND end_date >= %s";
    $params = [$shortcode, $current_date, $current_date];

    $promotions = $wpdb->get_results($wpdb->prepare($query, $params));

    foreach ($promotions as $promo) {
        if (is_null($promo->category) || in_array($promo->category, $product_categories)) {
            return shortcodes(esc_url($promo->image), esc_attr($promo->title))[$shortcode];
        }
    }

    return '';
}

function initialize_shortcodes() {
    $shortcodes = shortcode_names();
    foreach ($shortcodes as $shortcode => $label) {
        add_shortcode($shortcode, function($atts) use ($shortcode) {
            return handle_shortcode($atts, $shortcode);
        });
    }
}

function promotions_settings_page() {
    ?>
    <div class="wrap">
        <h1>Промоции</h1>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" class="bootstrap-form">
            <input type="hidden" name="action" value="submit_promo">

            <div class="form-group row">
                <label for="promo_image" class="col-sm-4 col-form-label">Качи изображение</label>
                <div class="col-sm-8">
                    <input type="file" class="form-control-file" name="promo_image" id="promo_image">
                    <img id="promo_image_preview" src="" alt="Selected Image" style="max-width: 300px; max-height: 300px; display: none;">
                </div>
            </div>
            
            <div class="form-group row">
                <label for="promo_title" class="col-sm-4 col-form-label">Позиция (shortcode)</label>
                <div class="col-sm-8">
                    <select class="form-control" name="promo_shortcode" id="promo_shortcode" placeholder="Позиция">
                        <option></option>
                        <?php foreach (shortcode_names() as $shortcode => $name) {
                            echo "<option value='$shortcode'>$name</option>";
                        } ?>
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <label for="promo_title" class="col-sm-4 col-form-label">Заглавие</label>
                <div class="col-sm-8">
                    <input type="text" class="form-control" name="promo_title" id="promo_title" placeholder="Заглавие">
                </div>
            </div>

            <div class="form-group row">
                <label for="promo_start_date" class="col-sm-4 col-form-label">Начална дата</label>
                <div class="col-sm-8">
                    <input type="text" class="form-control datepicker-input" name="promo_start_date" id="promo_start_date" />
                </div>
            </div>

            <div class="form-group row">
                <label for="promo_end_date" class="col-sm-4 col-form-label">Крайна дата</label>
                <div class="col-sm-8">
                    <input type="text" class="form-control datepicker-input" name="promo_end_date" id="promo_end_date" />
                </div>
            </div>

            <div class="form-group row" id="promo_categories" style="display:none">
                <label class="col-sm-2 col-form-label">Избери категории</label>
                <div class="col-sm-8">
                    <?php
                    $product_categories = get_terms([
                        'taxonomy' => 'product_cat',
                        'hide_empty' => false,
                    ]);
                
                    // Create a hierarchical array of categories
                    $categories_hierarchy = [];
                    foreach ($product_categories as $category) {
                        if ($category->parent == 0) {
                            // This is a base category
                            $categories_hierarchy[$category->term_id] = [
                                'category' => $category,
                                'children' => []
                            ];
                        } else {
                            // This is a subcategory
                            if (!isset($categories_hierarchy[$category->parent])) {
                                $categories_hierarchy[$category->parent] = [
                                    'category' => null,
                                    'children' => []
                                ];
                            }
                            $categories_hierarchy[$category->parent]['children'][] = $category;
                        }
                    }
                
                    // Display the categories
                    foreach ($categories_hierarchy as $category_info) {
                        if ($category_info['category']) {
                            // Display base category in bold
                            echo '<div class="form-group row"><strong><input type="checkbox" name="promo_categories[]" value="' . esc_attr($category_info['category']->term_id) . '" class="base-category" data-category-id="' . esc_attr($category_info['category']->term_id) . '" id="category_' . esc_attr($category_info['category']->term_id) . '">' . esc_html($category_info['category']->name) . '</strong></div>';
                        }
                
                        // Display subcategories
                        if (!empty($category_info['children'])) {
                            foreach ($category_info['children'] as $subcategory) {
                                echo '<div class="form-group row" style="padding-left: 20px;"><input type="checkbox" name="promo_categories[]" value="' . esc_attr($subcategory->term_id) . '" class="sub-category" data-parent-id="' . esc_attr($category_info['category']->term_id) . '" id="category_' . esc_attr($subcategory->term_id) . '">' . esc_html($subcategory->name) . '</div>';
                            }
                        }
                    }
                    ?>
                </div>
            </div>

            <div class="form-group row">
                <div class="col-sm-10 offset-sm-2">
                    <button type="submit" name="submit_promo" class="btn btn-primary">Запази</button>
                </div>
            </div>
        </form>
    </div>
    <?php
}

function promotions_list_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'promotions';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY end_date DESC");

    ?>
    <div class="wrap">
        <h1>Списък с промоции</h1>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Категория</th>
                    <th>Позиция</th>
                    <th>Заглавие</th>
                    <th>Изображение</th>
                    <th>Начална дата</th>
                    <th>Крайна дата</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row) : ?>
                    <tr <?php echo ($row->end_date < date('Y-m-d') ? 'style="opacity: 0.3"' : ''); ?>>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <td><?php echo esc_html($row->id); ?></td>
                            <td><?php echo esc_html(get_term($row->category)->name); ?></td>
                            <td>
                                <?php
                                    echo '<select name="promo_shortcode" id="promo_shortcode">';
                                    foreach (shortcode_names() as $shortcode => $name) {
                                        echo "<option value='$shortcode' ".($row->shortcode == $shortcode ? 'selected' : '').">$name</option>";
                                    }
                                    echo '</select>';
                                ?>
                            </td>
                            <td><textarea class="form-control" name="promo_title" id="promo_title"><?php echo esc_html($row->title); ?></textarea></td>
                            <td><a target="blank" href="<?php echo esc_url($row->image); ?>"><img src="<?php echo esc_url($row->image); ?>" alt="<?php echo esc_html($row->title); ?>" class="uploadedImages" style="width: 100px;"></a></td>
                            <td><input type="text" class="form-control datepicker-input" name="promo_start_date" id="promo_start_date" value="<?php echo date('d/m/Y', strtotime($row->start_date)); ?>" /></td>
                            <td><input type="text" class="form-control datepicker-input" name="promo_end_date" id="promo_end_date" value="<?php echo date('d/m/Y', strtotime($row->end_date)); ?>" /></td>
                            <td>
                                <input type="hidden" name="action" value="edit_promo">
                                <input type="hidden" name="promo_id" value="<?php echo esc_attr($row->id); ?>">
                                <button type="submit" class="btn btn-primary">Редактирай</button>
                            </td>
                        </form>
                        <td>
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <input type="hidden" name="action" value="delete_promo">
                                <input type="hidden" name="promo_id" value="<?php echo esc_attr($row->id); ?>">
                                <button type="submit" class="btn btn-danger">Изтрий</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" action="https://doormann.bg/wp-admin/admin-post.php">
            <input type="hidden" name="action" value="delete_expired">
            <button type="submit" class="btn btn-danger">Изтрий приключените</button>
        </form>
    </div>
    <?php
}


function handle_promotions_form() {
    if (isset($_POST['submit_promo'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'promotions';

        $promo_categories = isset($_POST['promo_categories']) ? $_POST['promo_categories'] : array();
        $promo_title = sanitize_text_field($_POST['promo_title']);
        $promo_shortcode = sanitize_text_field($_POST['promo_shortcode']);
        $promo_start_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['promo_start_date'])), 'Y-m-d');
        $promo_end_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['promo_end_date'])), 'Y-m-d');
        $upload_dir = wp_upload_dir();
        $promo_image = '';

        if (!empty($_FILES['promo_image']['tmp_name'])) {
            // Премести качения файл в директорията /promotions
            $upload_path = $upload_dir['basedir'] . '/promotions/';
            $upload_url = $upload_dir['baseurl'] . '/promotions/';

            // Създай директорията, ако не съществува
            if (!file_exists($upload_path)) {
                mkdir($upload_path, 0755, true);
            }

            $filename = basename($_FILES['promo_image']['name']);
            $target_file = $upload_path . $filename;

            if (move_uploaded_file($_FILES['promo_image']['tmp_name'], $target_file)) {
                $promo_image = $upload_url . $filename;
            }
        }

        // Вмъкни данните в потребителската таблица
        if (count($promo_categories) > 0) {
            foreach ($promo_categories as $category_id) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'category' => $category_id,
                        'title' => $promo_title,
                        'shortcode' => $promo_shortcode,
                        'image' => $promo_image,
                        'start_date' => $promo_start_date,
                        'end_date' => $promo_end_date
                    )
                );
            }
        } else {
            $wpdb->insert(
                    $table_name,
                    array(
                        'title' => $promo_title,
                        'shortcode' => $promo_shortcode,
                        'image' => $promo_image,
                        'start_date' => $promo_start_date,
                        'end_date' => $promo_end_date
                    )
                );
        }
        
        update_option('promo_message', 'Успешно запазване.');

        // Пренасочи, за да избегнеш повторно изпращане на формата
        wp_redirect(admin_url('admin.php?page=promotions_settings'));
        exit;
    }
}

function handle_edit_promo() {
    if (isset($_POST['action']) && $_POST['action'] == 'edit_promo' && isset($_POST['promo_id'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'promotions';
        $promo_id = intval($_POST['promo_id']);
        $promo_title = sanitize_text_field($_POST['promo_title']);
        $promo_shortcode = sanitize_text_field($_POST['promo_shortcode']);
        $promo_start_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['promo_start_date'])), 'Y-m-d');
        $promo_end_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['promo_end_date'])), 'Y-m-d');
            
        $wpdb->update($table_name, [
            'title' => $promo_title,
            'shortcode' => $promo_shortcode,
            'start_date' => $promo_start_date,
            'end_date' => $promo_end_date,
            ],
            [
                'id' => $promo_id
            ]
        );
        
        update_option('promo_message', 'Успешно редактиране.');

        wp_redirect(admin_url('admin.php?page=promotions'));
        exit;
    } else {
        var_dump($_POST);
        exit;
    }
}

function handle_delete_promo() {
    if (isset($_POST['action']) && $_POST['action'] == 'delete_promo' && isset($_POST['promo_id'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'promotions';
        $promo_id = intval($_POST['promo_id']);

        // Вземи данните за изображението преди да изтриеш записа
        $promo = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $promo_id));
        if ($promo) {
            // Преброй колко пъти се среща това изображение в базата данни
            $image_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE image = %s", $promo->image));
            
            // Изтрий записа от базата данни
            $wpdb->delete($table_name, array('id' => $promo_id));

            if ($image_count == 1) {
                $image_path = str_replace(site_url(), ABSPATH, $promo->image);
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
        }
        
        update_option('promo_message', 'Успешно изтриване.');

        // Пренасочи, за да избегнеш повторно изпращане на формата
        wp_redirect(admin_url('admin.php?page=promotions'));
        exit;
    } else {
        // За отстраняване на грешки
        var_dump($_POST);
        exit;
    }
}

function handle_delete_expired() {
    if (isset($_POST['action']) && $_POST['action'] == 'delete_expired') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'promotions';
        $today = date('Y-m-d');

        // Get all expired records
        $expired_promos = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE end_date < %s", $today));

        foreach ($expired_promos as $promo) {
            // Count how many times this image is referenced in the database
            $image_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE image = %s", $promo->image));
            
            // Delete the record from the database
            $wpdb->delete($table_name, array('id' => $promo->id));

            // If the image is only referenced once, delete the image file
            if ($image_count == 1) {
                $image_path = str_replace(site_url(), ABSPATH, $promo->image);
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
        }
        
        update_option('promo_message', 'Успешно изтриване.');

        // Redirect to avoid form resubmission
        wp_redirect(admin_url('admin.php?page=promotions'));
        exit;
    } else {
        // For debugging
        var_dump($_POST);
        exit;
    }
}

function promotions_menu() {
    add_menu_page(
        'Банери за промоции',
        'Промоции',
        'manage_options',
        'promotions',
        'promotions_list_page',
        'dashicons-money-alt',
        1
    );

    add_submenu_page(
        'promotions',
        'Добавяне на банер',
        'Добавяне на банер',
        'manage_options',
        'promotions_settings',
        'promotions_settings_page'
    );
}

function create_promotions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'promotions';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        category mediumint(9) NULL,
        shortcode varchar(255) NOT NULL,
        title varchar(255) NOT NULL,
        image varchar(255) NOT NULL,
        start_date date NOT NULL,
        end_date date NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function remove_admin_notices() {
    remove_all_actions('admin_notices');
    remove_all_actions('all_admin_notices');
}

function add_specific_admin_notice_back() {
    add_action('admin_notices', 'show_admin_message');
}

function show_admin_message() {
    $message = get_option('promo_message');
    if ($message) {
        echo '<div class="notice notice-success is-dismissible">
                <p>' . esc_html($message) . '</p>
              </div>';
        // Delete the option so the message doesn't persist
        delete_option('promo_message');
    }
}

register_activation_hook(__FILE__, 'create_promotions_table');

initialize_shortcodes();

add_action('admin_init', 'remove_admin_notices');
add_action('admin_init', 'add_specific_admin_notice_back');
add_action('admin_notices', 'show_admin_message');
add_action('admin_enqueue_scripts', 'load_bootstrap');
add_action('admin_enqueue_scripts', 'enqueue_promotions_script');
add_action('admin_menu', 'promotions_menu');
add_action('admin_post_submit_promo', 'handle_promotions_form');
add_action('admin_post_edit_promo', 'handle_edit_promo');
add_action('admin_post_delete_promo', 'handle_delete_promo');
add_action('admin_post_delete_expired', 'handle_delete_expired');