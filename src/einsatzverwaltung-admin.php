<?php
namespace abrain\Einsatzverwaltung;

use WP_Post;

/**
 * Regelt die Darstellung im Administrationsbereich
 */
class Admin
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->addHooks();
    }

    private function addHooks()
    {
        add_action('add_meta_boxes_einsatz', array($this, 'addMetaBoxes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueEditScripts'));
        add_filter('manage_edit-einsatz_columns', array($this, 'filterColumnsEinsatz'));
        add_action('manage_einsatz_posts_custom_column', array($this, 'filterColumnContentEinsatz'), 10, 2);
        add_action('dashboard_glance_items', array($this, 'addEinsatzberichteToDashboard')); // since WP 3.8
        add_action('right_now_content_table_end', array($this, 'addEinsatzberichteToDashboardLegacy')); // before WP 3.8
    }

    /**
     * Fügt die Metabox zum Bearbeiten der Einsatzdetails ein
     */
    public function addMetaBoxes()
    {
        add_meta_box(
            'einsatzverwaltung_meta_box',
            'Einsatzdetails',
            array($this, 'displayMetaBoxEinsatzdetails'),
            'einsatz',
            'normal',
            'high'
        );

        add_meta_box(
            'einsatzartdiv',
            'Einsatzart',
            array($this, 'displayMetaBoxEinsatzart'),
            'einsatz',
            'side',
            'high'
        );
    }

    /**
     * Zusätzliche Skripte im Admin-Bereich einbinden
     *
     * @param string $hook Name der aufgerufenen Datei
     */
    public function enqueueEditScripts($hook)
    {
        if ('post.php' == $hook || 'post-new.php' == $hook) {
            // Nur auf der Bearbeitungsseite anzeigen
            wp_enqueue_script(
                'einsatzverwaltung-edit-script',
                Core::$scriptUrl . 'einsatzverwaltung-edit.js',
                array('jquery', 'jquery-ui-autocomplete')
            );
            wp_enqueue_style(
                'einsatzverwaltung-edit',
                Core::$styleUrl . 'style-edit.css'
            );
        } elseif ('settings_page_einsatzvw-settings' == $hook) {
            wp_enqueue_script(
                'einsatzverwaltung-settings-script',
                Core::$scriptUrl . 'einsatzverwaltung-settings.js',
                array('jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-sortable')
            );
        }

        wp_enqueue_style(
            'einsatzverwaltung-fontawesome',
            Core::$pluginUrl . 'font-awesome/css/font-awesome.min.css'
        );
        wp_enqueue_style(
            'einsatzverwaltung-admin',
            Core::$styleUrl . 'style-admin.css'
        );
    }

    /**
     * Inhalt der Metabox zum Bearbeiten der Einsatzdetails
     *
     * @param WP_Post $post Das Post-Objekt des aktuell bearbeiteten Einsatzberichts
     */
    public function displayMetaBoxEinsatzdetails($post)
    {
        // Use nonce for verification
        wp_nonce_field('save_einsatz_details', 'einsatzverwaltung_nonce');

        // The actual fields for data entry
        // Use get_post_meta to retrieve an existing value from the database and use the value for the form
        $nummer = get_post_field('post_name', $post->ID);
        $alarmzeit = get_post_meta($post->ID, $key = 'einsatz_alarmzeit', $single = true);
        $einsatzende = get_post_meta($post->ID, $key = 'einsatz_einsatzende', $single = true);
        $einsatzort = get_post_meta($post->ID, $key = 'einsatz_einsatzort', $single = true);
        $einsatzleiter = get_post_meta($post->ID, $key = 'einsatz_einsatzleiter', $single = true);
        $fehlalarm = get_post_meta($post->ID, $key = 'einsatz_fehlalarm', $single = true);
        $mannschaftsstaerke = get_post_meta($post->ID, $key = 'einsatz_mannschaft', $single = true);

        $names = Data::getEinsatzleiter();
        echo '<input type="hidden" id="einsatzleiter_used_values" value="' . implode(',', $names) . '" />';

        echo '<table><tbody>';

        $this->echoInputText(
            __("Einsatznummer", 'einsatzverwaltung'),
            'einsatzverwaltung_nummer',
            esc_attr($nummer),
            Core::getNextEinsatznummer(date('Y')),
            10
        );

        $this->echoInputText(
            __("Alarmzeit", 'einsatzverwaltung'),
            'einsatzverwaltung_alarmzeit',
            esc_attr($alarmzeit),
            'JJJJ-MM-TT hh:mm'
        );

        $this->echoInputText(
            __("Einsatzende", 'einsatzverwaltung'),
            'einsatzverwaltung_einsatzende',
            esc_attr($einsatzende),
            'JJJJ-MM-TT hh:mm'
        );

        $this->echoInputCheckbox(
            __("Fehlalarm", 'einsatzverwaltung'),
            'einsatzverwaltung_fehlalarm',
            $fehlalarm
        );

        echo '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>';

        $this->echoInputText(
            __("Einsatzort", 'einsatzverwaltung'),
            'einsatzverwaltung_einsatzort',
            esc_attr($einsatzort)
        );

        $this->echoInputText(
            __("Einsatzleiter", 'einsatzverwaltung'),
            'einsatzverwaltung_einsatzleiter',
            esc_attr($einsatzleiter)
        );

        $this->echoInputText(
            __("Mannschaftsst&auml;rke", 'einsatzverwaltung'),
            'einsatzverwaltung_mannschaft',
            esc_attr($mannschaftsstaerke)
        );

        echo '</tbody></table>';
    }

    /**
     * Gibt ein Eingabefeld für die Metabox aus
     *
     * @param string $label Beschriftung
     * @param string $name Feld-ID
     * @param string $value Feldwert
     * @param string $placeholder Platzhalter
     * @param int $size Größe des Eingabefelds
     */
    private function echoInputText($label, $name, $value, $placeholder = '', $size = 20)
    {
        echo '<tr><td><label for="' . $name . '">' . $label . '</label></td>';
        echo '<td><input type="text" id="' . $name . '" name="' . $name . '" value="'.$value.'" size="' . $size . '" ';
        if (!empty($placeholder)) {
            echo 'placeholder="'.$placeholder.'" ';
        }
        echo '/></td></tr>';
    }

    /**
     * Gibt eine Checkbox für die Metabox aus
     *
     * @param string $label Beschriftung
     * @param string $name Feld-ID
     * @param mixed $state Zustandswert
     */
    private function echoInputCheckbox($label, $name, $state)
    {
        echo '<tr><td><label for="' . $name . '">' . $label . '</label></td>';
        echo '<td><input type="checkbox" id="' . $name . '" name="' . $name . '" value="1" ';
        echo Utilities::checked($state) . '/></td></tr>';
    }

    /**
     * Zeigt die Metabox für die Einsatzart
     *
     * @param WP_Post $post Post-Object
     */
    public function displayMetaBoxEinsatzart($post)
    {
        $einsatzart = Data::getEinsatzart($post->ID);
        Frontend::dropdownEinsatzart($einsatzart ? $einsatzart->term_id : 0);
    }

    /**
     * Legt fest, welche Spalten bei der Übersicht der Einsatzberichte im
     * Adminbereich angezeigt werden
     *
     * @param array $columns
     *
     * @return array
     */
    public function filterColumnsEinsatz($columns)
    {
        unset($columns['author']);
        unset($columns['date']);
        $columns['title'] = __('Einsatzbericht', 'einsatzverwaltung');
        $columns['e_nummer'] = __('Nummer', 'einsatzverwaltung');
        $columns['e_alarmzeit'] = __('Alarmzeit', 'einsatzverwaltung');
        $columns['e_einsatzende'] = __('Einsatzende', 'einsatzverwaltung');
        $columns['e_art'] = __('Art', 'einsatzverwaltung');
        $columns['e_fzg'] = __('Fahrzeuge', 'einsatzverwaltung');

        return $columns;
    }

    /**
     * Liefert den Inhalt für die jeweiligen Spalten bei der Übersicht der
     * Einsatzberichte im Adminbereich
     *
     * @param string $column
     * @param int $post_id
     */
    public function filterColumnContentEinsatz($column, $post_id)
    {
        global $post;

        switch($column) {
            case 'e_nummer':
                $einsatz_nummer = get_post_field('post_name', $post_id);
                echo (empty($einsatz_nummer) ? '-' : $einsatz_nummer);
                break;
            case 'e_einsatzende':
                $einsatz_einsatzende = get_post_meta($post_id, 'einsatz_einsatzende', true);
                if (empty($einsatz_einsatzende)) {
                    echo '-';
                } else {
                    $timestamp = strtotime($einsatz_einsatzende);
                    echo date("d.m.Y", $timestamp)."<br>".date("H:i", $timestamp);
                }
                break;
            case 'e_alarmzeit':
                $einsatz_alarmzeit = get_post_meta($post_id, 'einsatz_alarmzeit', true);

                if (empty($einsatz_alarmzeit)) {
                    echo '-';
                } else {
                    $timestamp = strtotime($einsatz_alarmzeit);
                    echo date("d.m.Y", $timestamp)."<br>".date("H:i", $timestamp);
                }
                break;
            case 'e_art':
                $term = Data::getEinsatzart($post_id);
                if ($term) {
                    $url = esc_url(
                        add_query_arg(
                            array('post_type' => $post->post_type, 'einsatzart' => $term->slug),
                            'edit.php'
                        )
                    );
                    $text = esc_html(sanitize_term_field('name', $term->name, $term->term_id, 'einsatzart', 'display'));
                    echo '<a href="' . $url . '">' . $text . '</a>';
                } else {
                    echo '-';
                }
                break;
            case 'e_fzg':
                $terms = get_the_terms($post_id, 'fahrzeug');

                if (!empty($terms)) {
                    $out = array();
                    foreach ($terms as $term) {
                        $url = esc_url(
                            add_query_arg(
                                array('post_type' => $post->post_type, 'fahrzeug' => $term->slug),
                                'edit.php'
                            )
                        );
                        $text = esc_html(
                            sanitize_term_field('name', $term->name, $term->term_id, 'fahrzeug', 'display')
                        );
                        $out[] = '<a href="' . $url . '">' . $text . '</a>';
                    }
                    echo join(', ', $out);
                } else {
                    echo '-';
                }
                break;
            default:
                break;
        }
    }

    /**
     * Zahl der Einsatzberichte im Dashboard anzeigen
     *
     * @param array $items
     *
     * @return array
     */
    public function addEinsatzberichteToDashboard($items)
    {
        $postType = 'einsatz';
        if (post_type_exists($postType)) {
            $pt_info = get_post_type_object($postType); // get a specific CPT's details
            $num_posts = wp_count_posts($postType); // retrieve number of posts associated with this CPT
            $num = number_format_i18n($num_posts->publish); // number of published posts for this CPT
            // singular/plural text label for CPT
            $text = _n($pt_info->labels->singular_name, $pt_info->labels->name, intval($num_posts->publish));
            echo '<li class="'.$pt_info->name.'-count page-count">';
            if (current_user_can('edit_einsatzberichte')) {
                echo '<a href="edit.php?post_type='.$postType.'">'.$num.' '.$text.'</a>';
            } else {
                echo '<span>'.$num.' '.$text.'</span>';
            }
            echo '</li>';
        }

        return $items;
    }

    /**
     * Zahl der Einsatzberichte im Dashboard anzeigen (für WordPress 3.7 und älter)
     */
    public function addEinsatzberichteToDashboardLegacy()
    {
        if (post_type_exists('einsatz')) {
            $postType = 'einsatz';
            $pt_info = get_post_type_object($postType); // get a specific CPT's details
            $num_posts = wp_count_posts($postType); // retrieve number of posts associated with this CPT
            $num = number_format_i18n($num_posts->publish); // number of published posts for this CPT
            // singular/plural text label for CPT
            $text = _n($pt_info->labels->singular_name, $pt_info->labels->name, intval($num_posts->publish));
            echo '<tr><td class="first b">';
            if (current_user_can('edit_einsatzberichte')) {
                echo '<a href="edit.php?post_type='.$postType.'">'.$num.'</a>';
            } else {
                echo $num;
            }
            echo '</td><td class="t">';
            if (current_user_can('edit_einsatzberichte')) {
                echo '<a href="edit.php?post_type='.$postType.'">'.$text.'</a>';
            } else {
                echo $text;
            }
            echo '</td></tr>';
        }
    }
}