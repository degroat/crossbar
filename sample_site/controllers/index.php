<?

class index_controller extends base_controller
{
	public function index()
	{
		// ------------------------------------------------------
		// This is where you'd put the logic for your index page
		// For this sample site, there isn't much so we're going
		// to ust set a variable and then print it out in the
		// view, which can be found in views/index/index.phtml
		// ------------------------------------------------------
		$this->foo = "This is the value off \$this->foo set in the index_controller and function index()";
	}

}

?>
