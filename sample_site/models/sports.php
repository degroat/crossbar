<?
// ----------------------------------
// This is a sample model w/ a sample
// use of the included mysql class
// ----------------------------------

class sports
{
	public function __construct()
	{
	}

	public function get_sports()
	{
		$sql = "select * from sports";
		$result = mysql::query('main', $sql);
		if(PEAR::isError($result))
		{
			return FALSE;
		}
		foreach($result as $row)
		{
			$sports[$row['sport']] = $row;
		}
		return $sports;
	}

}
?>
