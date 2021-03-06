<?php
include "../../../include/db.php";
include_once "../../../include/general.php";
include "../../../include/search_functions.php";
include "../../../include/resource_functions.php";
include "../../../include/image_processing.php";
include_once "../../../include/collections_functions.php";
include "../../../include/authenticate.php";
include "../include/xml_functions.php";

$xml_source=getvalescaped("xml","");
$xml_raw         = getval('xml', '');

/*
$xml_source='
<resourceset>
<resource type="1">
<keyfield ref="8">556677</keyfield>

<collection>numeric id of collection</collection> 
<FILENAME>/dir/dir/filename_of_image.jpg</FILENAME>

<field ref="18">my description of the picture</field>
<field ref="3">Uganda</field>

</resource>
<resource type="1">
<keyfield ref="8">556688</keyfield>

<collection>numeric id of collection</collection> 
<FILENAME>/somewhere/New Bitmap Image.bmpx</FILENAME>

<field ref="18">Foobar</field>
<field ref="3">Mexico</field>

</resource>
</resourceset>
';
*/

$sign=md5($scramble_key . $xml_source);
if (getval("sign","")!=$sign) {exit("Invalid signature");}

$xml=parse_xml_into_array($xml_raw);
echo "<pre>";

$resources=get_nodes_by_tag("RESOURCE");
foreach ($resources as $resource)
	{
	# For each resource
	
	# Find keyfield info
	$keyfields=get_nodes_by_tag("KEYFIELD",$resource["id"]);
	if (count($keyfields)!==1) {exit("There must be exactly one 'keyfield' element for each resource.<br/>");}
	$keyfield=$keyfields[0];

	# Search for a matching resource
    $ref = get_resources_by_resource_data_value(escape_check($keyfield['attributes']['REF']), escape_check(trim($keyfield['value'])));
	if ($ref==0)
		{
		# No matching resource found. Insert a new resource.
		echo "<br><br>" . $keyfield["value"] . ": No resource found.";
		
		# Add a new resource and set the key field
		$ref=create_resource($resource["attributes"]["TYPE"]);
		update_field($ref,$keyfield["attributes"]["REF"],$keyfield["value"]);
		}
	else
		{
		# Existing resource found.
		echo "<br><br>" . $keyfield["value"] . ": Existing resource.";
		}
		
	# Update metadata fields
    $fields=get_nodes_by_tag("FIELD",$resource["id"]);
    foreach ($fields as $field)
        {
        $value = '';

        $fieldtype = sql_value("SELECT type `value` FROM resource_type_field WHERE ref = '{$field['attributes']['REF']}'", 1);

        if(isset($field['value']) && 8 == $fieldtype)
            {
            // HTML type - value should have been encoded for submission
            $value = htmlspecialchars_decode($field['value']);
            }
        else if(isset($field['value']))
            {
            $value = $field['value'];
            }

        echo "<br>{$field['attributes']['REF']} = {$value}";

        update_field($ref, $field['attributes']['REF'], $value);
        }
        
     # Add to collections
    $fields=get_nodes_by_tag("COLLECTION",$resource["id"]);		
    foreach ($fields as $field)
        {
        $collection=get_collection($field["value"]);
        $collectionname=$collection["name"];
        echo "<br>collection=" . $collection["name"] . " (" . $field["value"] . ")";
        add_resource_to_collection($ref,$field["value"]);
        }
		
	# Update resource type
	update_resource_type($ref,$resource["attributes"]["TYPE"]);
	
	# Update file, if specified
	$filenames=get_nodes_by_tag("FILENAME",$resource["id"]);
	if (count($filenames)==1)
		{
		$filename=$filenames[0]["value"];
		echo "<br>Processing file: " . $filename;
		if (!file_exists($filename))
			{
			echo "<br/>File does not exist!";
			}
		else
			{
			# Get path
			$extension=explode(".",$filename);
			if(count($extension)>1)
				{
				$extension=trim(strtolower($extension[count($extension)-1]));

                // Save the file extension in the database
                sql_query('UPDATE resource SET file_extension = "' . escape_check($extension) . '" WHERE ref = "' . escape_check($ref) . '";');
				} 
			else
				{
				$extension="";
				}
			
			$path=get_resource_path($ref,true,"",true,$extension);
			echo "<br/>Resource path is $path";
			copy($filename,$path);
			echo "...creating previews...";
			create_previews($ref,false,$extension);
			echo "...done.";
			}
		
		}
	
	}



