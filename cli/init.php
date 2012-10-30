<?

# GET DOMAIN FROM PARMETERS
if(!isset($_SERVER['argv'][1]))
{
    print "ERROR: Missing required domain";
    exit;
}
$domain = $_SERVER['argv'][1];

# GET PATHS
$crossbar_path = str_replace("cli/init.php", "", $_SERVER['SCRIPT_NAME']);
$site_path = $_SERVER['PWD'] . "/";

# VERIFY WE ARE IN AN EMPTY DIRECTORY
if(count(scandir($site_path)) > 2)
{
    print "ERROR: Target directory is not empty";
    exit;
}

# CREATE DIRECTORIES
$top = array("art","conf","controllers","docs","htdocs","htdocs/images","htdocs/css","htdocs/js","layouts","logs","models","modules","scripts","utilities","views", "views/index");
foreach($top as $dir)
{
    if(mkdir($dir) === FALSE)
    {
        print "ERROR: Unable to create directory: {$site_path}{$dir}";
        exit;
    }
    $filename = $site_path . $dir . "/.keepdir";
    file_put_contents($filename, "This file is here so this directory is committed into a Git or Mercurial repo");
}

# SET UP BOOTSTRAP
$bootstrap = file_get_contents($crossbar_path . "cli/resources/sample_index.php");
if($crossbar_path[0] == "/") 
{
    $bootstrap = str_replace("###CROSSBAR_PATH###", $crossbar_path, $bootstrap);
}
else
{
    $bootstrap = str_replace("###CROSSBAR_PATH###", "../".$crossbar_path, $bootstrap);
}
$filename = $site_path . "htdocs/index.php";
if(file_put_contents($filename, $bootstrap) === FALSE)
{
    print "ERROR: Unable to write to {$filename}";
    exit;
}   

# SET UP CONF 
$conf = file_get_contents($crossbar_path . "cli/resources/sample_apache.conf");
$conf = str_replace("###SITE_PATH###", $site_path, $conf);
$conf = str_replace("###DOMAIN###", $domain, $conf);
$filename = $site_path . "conf/apache.conf";
if(file_put_contents($filename, $conf) === FALSE)
{
    print "ERROR: Unable to write to {$filename}";
    exit;
}   

# SET UP LAYOUT
$layout = file_get_contents($crossbar_path . "cli/resources/sample_layout.phtml");
$layout = str_replace("###DOMAIN###", $domain, $layout);
$filename = $site_path . "layouts/default.phtml";
if(file_put_contents($filename, $layout) === FALSE)
{
    print "ERROR: Unable to write to {$filename}";
    exit;
}   


# COPY ADDITIONAL FILES
$files = array(
    $crossbar_path . "cli/resources/sample_controller.php" => $site_path . "controllers/index.php",
    $crossbar_path . "cli/resources/sample_main.css" => $site_path . "htdocs/css/main.css",
    $crossbar_path . "cli/resources/sample_main.js" => $site_path . "htdocs/js/main.js",
    $crossbar_path . "cli/resources/sample_view.phtml" => $site_path . "views/index/index.phtml",
    $crossbar_path . "cli/resources/.gitignore" => $site_path . ".gitignore",
);
foreach($files as $from => $to)
{
    if(copy($from, $to) === FALSE)
    {
        print "ERROR: Unable to write to {$to}";
        exit;
    }
}

# SET UP CONF 
$jquery = file_get_contents("http://code.jquery.com/jquery.min.js");
$filename = $site_path . "htdocs/js/jquery.min.js";
if(file_put_contents($filename, $conf) === FALSE)
{
    print "ERROR: Unable to write to {$filename}";
    exit;
}

$cmd = "cd {$site_path}htdocs; wget http://twitter.github.com/bootstrap/assets/bootstrap.zip; unzip bootstrap.zip; rm bootstrap.zip;";
print "$cmd\n";
$response = shell_exec($cmd);


print "SUCCESS!\n\n";
?>
