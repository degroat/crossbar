<?

class misc 
{
    public static function slug($text)
    {
        $slug = trim($text);
        $slug = preg_replace('/[^A-Za-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', "-", $slug);   // collapse multiple dashes to one
        $slug = preg_replace('/^-/', '', $slug);    // strip off preceding dash
        $slug = preg_replace('/-$/', '', $slug);    // strip off trailing dash
        return $slug;
    }
}

?>
