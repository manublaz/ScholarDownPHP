<html>
<head>
	<title>ScholarDownPHP</title>
	<style>
        body { font-family: 'Arial', sans-serif; background-color: #f4f4f4;  margin: 30; padding: 0; }
        h1 { text-align: center; color: #333; }
        form { max-width: 550px; margin: 20px auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        div { margin-bottom: 15px; }
        input[type='text'], input[type='submit'] { width: 100%; padding: 10px; margin-top: 5px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }       
        input[type='submit'] { background-color: #4caf50; color: #fff; cursor: pointer; }
        input[type='submit']:hover { background-color: #45a049; }
        i { font-style: italic; color: #888; }
	</style>
</head>
<body>

<?php
include("functions.php");
if (isset($_POST['go1'])){ 
    $initProcess=""; 
} else { 
    $initProcess="<div><input type='submit' name='go1' value='Comenzar proceso'/></div>"; 
}

echo "
<h1>ScholarDownPHP</h1>
<form action='$_SERVER[PHP_SELF]' method='post'>
    <div><input type='text' name='start' class='' value='" . (isset($_POST['start']) ? $_POST['start'] : '0') . "'/> Número de resultado inicial. <i>El resultado inicial será 0, posteriormente se suma 10 por cada página de resultados</i></div>
    <div><input type='text' name='query' class='' value='" . (isset($_POST['query']) ? $_POST['query'] : '') . "'/> Consulta del usuario</div>
    $initProcess
</form>
";


if (isset($_POST['go1'])) {
    if (is_numeric($_POST['start']) && !empty($_POST['query'])) {
        
            // Guardar el registro en el archivo de log
            $fechaHora = date('Y-m-d H:i:s');
            $consulta = $_POST['query'];
            $start = $_POST['start'];
            $rango = "$start-" . ($start + 10);
            guardarRegistro($fechaHora, $consulta, $rango);
        
        // Generar la consulta y proporcionar el botón de continuar a la siguiente página
        $start=$_POST['start']; $nextStart=$start+10;
        $nextQuery=$_POST['query']; $query = urlencode($_POST['query']); 
        // URL de la página de resultados de Google Scholar
        $url = "https://scholar.google.es/scholar?lr=lang_en&as_vis=1&as_ylo=2000&as_yhi=2024&start=$start&as_sdt=0%2C5&q=$query+filetype%3Apdf";
        echo "
        <form action='$_SERVER[PHP_SELF]' method='post'>
            <input type='hidden' name='start' value='$nextStart'/>
            <input type='hidden' name='query' value='$nextQuery'/>
            <div>
                <div>Consulta generada <a href='$url'>$url</a></div>
                <div>Pasar a la siguiente página <input type='submit' name='go1' value='Siguiente página'/></div>
            </div>
        </form>
        ";
        
        // Obtener el contenido HTML de la página de Google Scholar
        $html = file_get_contents($url);
        
        // Crear un objeto DOMDocument
        $dom = new DOMDocument;
        
        // Cargar el HTML en el objeto DOMDocument, manejar los errores si es necesario
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        // Crear un objeto DOMXPath
        $xpath = new DOMXPath($dom);
        $pdfLinkNodeList = $xpath->query("//h3[@class='gs_rt']/a/@href");
        $citationNodeList = $xpath->query("//h3[@class='gs_rt']");

        // Verificar si se encontraron resultados antes de acceder a los nodos
        if($pdfLinkNodeList->length > 0) {
            
            // Iterar a través de los nodos para extraer títulos y enlaces de PDF
            for($i=0; $i<$pdfLinkNodeList->length; $i++) {
                
                $pdfLink = $pdfLinkNodeList->item($i)->nodeValue;
                $citation = $citationNodeList->item($i)->textContent;
                $citation = preg_replace("/(PDF|\[|\])/", "", $citation);
                $citation = remove_accents($citation);
                $allowedCharacters = array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'), ['_', '-']);
                $citation = filterAllowedCharacters($citation, $allowedCharacters);
                $citation = trim($citation);
                $citation = preg_replace("/(      |     |    |   |  | )/", " ", $citation);
                
                // Imprimir los resultados o almacenarlos como desees
                echo "<div>";
                echo "<div>Cita: $citation</div>";
                echo "<div>Enlace PDF: <a href='$pdfLink' target='_blank'>$pdfLink</a></div>";
                echo "</div>";
                    
                // Puedes ejecutar la función downloadPDF aquí si deseas descargar el PDF
                downloadPDF($pdfLink, $citation);
                echo "<br/><br/>";
                
            }
            
        }
        
    } else {
        
        echo "<span>Error. Falta número inicial o consulta</span>";
        
    }
}


// Función para descargar el PDF (puedes implementar según tus necesidades)
function downloadPDF($url, $title) {
    // Carpeta de descargas
    $downloadFolder = 'downloads';
    
    // Crear la carpeta de descargas si no existe
    if (!file_exists($downloadFolder)) {
        mkdir($downloadFolder, 0755, true);
    }
    
    $filePath = "$downloadFolder/$title.pdf";
    
    $ch = curl_init($url);
    // Establecer opciones de cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 segundos de tiempo de espera
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    
    // Ejecutar cURL y obtener el contenido del PDF
    $pdfContent = curl_exec($ch);
    
    // Verificar si la solicitud fue exitosa (código de respuesta 200)
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Verificar si se pudo obtener el contenido del PDF y la respuesta HTTP fue 200
    if ($pdfContent !== false && $httpCode === 200) {
        // Guardar el contenido en un archivo PDF
        file_put_contents($filePath, $pdfContent);
        echo "<span style='color: green;'>OK</span>\n";
    } else {
        // Manejar errores
        if ($httpCode !== 200) {
            echo "<span style='color: red;'>Error</span> <span>$title (HTTP $httpCode)</span>\n";
        } else {
            echo "<span style='color: red;'>Error</span> <span>$title</span>\n";
        }
    }
    
    // Cerrar la sesión cURL
    curl_close($ch);
    
    return $filePath;
}


?>

</body>
</html>
