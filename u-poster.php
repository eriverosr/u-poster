<?php
/**
 * Plugin Name: U-Poster
 * Plugin URI: http://github.com/adderou/uposter
 * Description: Permite difundir links en el foro de U-Cursos cuando se publica un post en el blog.
 * Version: 1.0.1
 * Author: Eduardo Riveros
 * Author URI: http://adderou.cl
 * License: GPL2
 */

 defined( 'ABSPATH' ) or die( 'Shoo shoo!' );

 add_action( 'transition_post_status', 'post_in_ucursos', 10, 3 );


 function generate_ucursos_excerpt($post) {
     setup_postdata($post);
	 $image = "";
	 if (has_post_thumbnail( $post->ID ) ) {
		$image = "<img src=\"".wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail' )[0]."\"/>";
	 }
	 return "Información desde <a href=\"" . 
            get_permalink($post) . 
            "\"> el blog de la comunidad</a>.\n\n" .
		 	$image."\n\n".
			strip_tags($post->post_content,"<img><a><u><strong><em><i><p><br><ul><li><ol>");
 }

 function post_in_ucursos( $new_status, $old_status, $post ) {
    // Only if post is new
    if ( $old_status != 'publish' && $new_status == 'publish' ) {
        $login_url = 'https://www.u-cursos.cl/upasaporte/api';
        $post_url = get_option('uposter_url') . "post";

        $login_data = array(
            'username' => get_option('uposter_username'),
            'password' => get_option('uposter_password'),
            'servicio' => 'ucursos_movil',
            'recordar' => '0',
            'extras[os]' =>  'uposter',
            'extras[v]' => '1000', // Si no, la api no pesca
        );

        $login_options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($login_data)
            )
        );
        
        $login_context  = stream_context_create($login_options);
        $login_result = file_get_contents($login_url, false, $login_context);

        if ($login_result === FALSE) {
            var_dump($login_result);
            die();
        }

        $login_response = json_decode($login_result);
        if ($login_response->status != 200) {
            die();
        }

        $session = $login_response->token;

        $post_options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\nCookie: PHPSESSID=" . $session . ";\r\n",
                'method'  => 'POST',
            )
        );

		// Esto nos dará el formulario (del cual sacaremos el csrf)
        $post_context  = @stream_context_create($post_options);
        $post_result = @file_get_contents($post_url, false, $post_context);
		// Conseguimos el csrf y luego hacemos el post real
		$marker = '<input type="hidden" name="csrf" value="';
		$csrf_ini = strpos($post_result, $marker) + strlen($marker);
 		$csrf_end = strpos($post_result, '"', $csrf_ini);
		$csrf = substr($post_result, $csrf_ini, $csrf_end - $csrf_ini);
		// Conseguimos ese valor mágico que hace que los post sean interpretados como HTML
		$marker2 = '<input type="hidden" id="txt_id_descripcion" name="_textarea[descripcion]" value="';
		$desc_ini = strpos($post_result, $marker2) + strlen($marker2);
 		$desc_end = strpos($post_result, '"', $desc_ini);		
		$descripcion = substr($post_result, $desc_ini, $desc_end - $desc_ini);
		
		// Ahora sí publicamos!		
		$post_data = array(
            'accion' => 'guardar',
            'titulo' => mb_convert_encoding($post->post_title, 'Windows-1252'),
			'comentarios' => -1,
			'visibilidad' => 1,
            'descripcion' => mb_convert_encoding(generate_ucursos_excerpt($post), 'HTML-ENTITIES'),
			'csrf' => $csrf,
			'_textarea[descripcion]' => $descripcion,
			
        );
		
		$post_options['http']['content'] = http_build_query($post_data);
		$post_context  = @stream_context_create($post_options);
        $post_result = @file_get_contents($post_url, false, $post_context);
		
    }
}

 add_action('admin_menu', 'uposter_menu');

function uposter_menu() {
	add_menu_page('Configuración de U-Poster', 'U-Poster', 'administrator', 'adderou-uposter', 'uposter_settings_page', 'dashicons-admin-generic');
}

function uposter_settings_page() { ?>
    <div class="wrap">
    <?php settings_errors(); ?>
    <h2>Configuración U-Poster</h2>
    <p><small>La contraseña queda guardada en el servidor, pero es necesario ingresarla nuevamente cada vez que se actualiza esta página por seguridad.</small></p>
    <form method="post" action="options.php">
        <?php settings_fields( 'uposter-settings' ); ?>
        <?php do_settings_sections( 'uposter-settings' ); ?>
        <table class="form-table">
            <tr valign="top">
            <th scope="row">Nombre de Usuario en U-Cursos</th>
            <td><input type="text" required name="uposter_username" value="<?php echo esc_attr( get_option('uposter_username') ); ?>" /></td>
            </tr>
             
            <tr valign="top">
            <th scope="row">Contraseña</th>
            <td><input type="password" required name="uposter_password" value="" /></td>
            </tr>
            
            <tr valign="top">
            <th scope="row">URL Novedades Comunidad</th>
            <td><input type="text" required name="uposter_url" value="<?php echo esc_attr( get_option('uposter_url') ); ?>" /></td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    
    </form>
    </div>
<?php }



add_action( 'admin_init', 'uposter_settings' );

function uposter_settings() {
	register_setting( 'uposter-settings', 'uposter_username' );
	register_setting( 'uposter-settings', 'uposter_password' );
	register_setting( 'uposter-settings', 'uposter_url' );
	register_setting( 'uposter-settings', 'uposter_category' );
}

