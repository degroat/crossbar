<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>API Documentation - <?=$_SERVER['HTTP_HOST'] ?></title>
        <link href="http://twitter.github.com/bootstrap/assets/css/bootstrap.css" rel="stylesheet">
        <style>
          body {
            padding-top: 60px; /* 60px to make the container go all the way to the bottom of the topbar */
          }
        </style>
    </head>
    <body>
    <div class="navbar navbar-fixed-top">
        <div class="navbar-inner">
            <div class="container">
                <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </a>
                <a class="brand" href="#">API Documentation</a>
                <div class="nav-collapse">
                    <ul class="nav">
                        <?
                        foreach($this->apis as $table => $apis)
                        {
                            ?>
                            <li><a href="#<?=$table ?>"><?=$table ?></a></li>
                            <?
                        }
                        ?>
                    </ul>
                </div><!--/.nav-collapse -->
            </div>
        </div>
    </div>
    <div class="container">

<?
foreach($this->apis as $table => $apis)
{

    ?>
    <a name="<?=$table ?>"></a>
    <div class="hero-unit">
        <h1><?=$table ?></h1> 
        <?
        foreach($apis as $action => $config)
        {
            ?>
            <a href="#<?=$table ?>_<?=$action ?>" class="btn btn-primary"><?=$action ?></a>
            <?
        }
        ?>
    </div>
    <?
    foreach($apis as $action => $config)
    {
        $example_params = array('_key' => 'YOUR_API_KEY');
        foreach($config['params'] as $param => $details)
        {
            if(isset($details['example']))
            {
                $example_params[$param] = $details['example'];
            }
        }
        $example_url = 'http' . ((isset($_SERVER['HTTPS'])) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . "/{$table}/{$action}?" . http_build_query($example_params);
        ?>
        <a name="<?=$table ?>_<?=$action ?>"></a>
        <a href="<?=$example_url ?>" class="btn btn-primary" target="_blank" style="float:right">Sample URL</a>
        <h2>/<?=$table ?>/<?=$action ?></h2>
        <table class="table table-striped table-bordered">
            <tr>
                <th>Parameter</th>
                <th>Required?</th>
                <th>Example</th>
            </tr>
        <?
        $example_params = array();
        foreach($config['params'] as $param => $details)
        {
            if(isset($details['example']))
            {
                $example_params[$param] = $details['example'];
            }
            ?>
            <tr>
                <td><?=$param ?></td>
                <td><?=($details['required']) ? 'Y' : 'N'  ?></td>
                <td><?=(isset($details['example'])) ? $details['example'] : '' ?></td>
            </tr>
            <?
        }
        ?>
        </table>
        <?



    }
}

?>
    </div> <!-- /container -->
    <script src="http://twitter.github.com/bootstrap/assets/js/bootstrap.js"></script>
  </body>
</html>


