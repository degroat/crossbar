<?

class geocode
{
    public static function address($address, $sensor = 'false')
    {
        $url = 'http://maps.googleapis.com/maps/api/geocode/json?' . http_build_query(array('address' => $address, 'sensor' => $sensor));
        $response = json_decode(curl::get($url), TRUE);
        if(count($response['results']) == 0)
        {
            return FALSE;
        }

        $address = array(
                        'address' => $response['results'][0]['formatted_address'],
                        'lat' => $response['results'][0]['geometry']['location']['lat'],
                        'lon' => $response['results'][0]['geometry']['location']['lng'],
                        'street_number' => NULL,
                        'street' => NULL,
                        'city' => NULL,
                        'state' => NULL,
                        'country' => NULL,
                        'county' => NULL,
                        'zip' => NULL,
                    );

        $conversion = array(
                        "street_number" => "street_number",
                        "route" => "street",
                        "locality" => "city",
                        "administrative_area_level_1" => "state",
                        "administrative_area_level_2" => "county",
                        "country" => "country",
                        "postal_code" => "zip",
                    );

        foreach($response['results'][0]['address_components'] as $component)
        {
            if(isset($conversion[$component['types'][0]]))
            {
                $address[$conversion[$component['types'][0]]] = $component['short_name'];
            }
        }

        return $address;
    }
}

?>
