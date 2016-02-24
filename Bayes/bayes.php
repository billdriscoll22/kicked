<?php
$dbname = "./bayes.db";
$lambda = 1;

try {
  $db = new PDO("sqlite:" . $dbname);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  echo "SQLite connection failed: ".$e->getMessage();
  exit();
}

function dbQuery($myQuery, $db) {
  $resultArray = array();
  try {
    $query = $db->prepare($myQuery);
    $query->execute();
    while($result = $query->fetch(PDO::FETCH_ASSOC)) {
      array_push($resultArray, $result);
    }  
    return $resultArray;
  } catch(PDOException $e) {
    #echo $e->getmessage();
  }
  return false;
}

$total = dbQuery('select count(*) from Cars', $db);
$total = $total[0]["count(*)"]; 
$sampleRow = dbQuery('select * from Cars limit 1', $db);
$featuresTemp = array_keys($sampleRow[0]);
$features = array_slice($featuresTemp, 2);
$numVar = 0;
$norm = array();
#Counting the number of variables
foreach($features as $feature):
	$query = "select count(Distinct " . $feature . ") from Cars";
	$result = dbQuery($query, $db);
	#echo var_dump($result) . "\n";
	$indexString = "count(Distinct " . $feature .  ")";
	$numVar = $result[0][$indexString];
	$norm[$feature] = floatval($total + ($numVar * $lambda * 2) + 1);
endforeach;
#This is initializing constants using laplace smoothing.

$query = "select count(*) from Cars where IsBadBuy = 1";
$indexString = "count(*)";
$result = dbQuery($query, $db);
$pKicked = ($result[0][$indexString] + $lambda)/($total + 2);
$pVariableArray = array();
$pVariableAndKickedArray = array();

foreach($features as $feature):
	#echo $feature . "-----------------------\n";
	$query = "select Distinct " . $feature . " from Cars";
	$result = dbQuery($query, $db);
	$pVariableArray[$feature] = array();
	$pVariableAndKickedArray[$feature] = array();
	foreach($result as $dummyArray):
		$variable = $dummyArray[$feature];
		if($variable != ""):
			if(is_numeric($variable)):
				$query = "select count(*) from Cars where " . $feature . " = " . $variable;
			else:
				$query = "select count(*) from Cars where " . $feature . " = \"" . $variable . "\"";
			endif;
			$result = dbQuery($query, $db);
			$count = $result[0]["count(*)"];
			$pVariableArray[$feature][$variable] = ($count + (2 * $lambda))/$norm[$feature];
			if(is_numeric($variable)):
				$query = "select count(*) from Cars where IsBadBuy = 1 and " . $feature . " = " . $variable;
			else:
				$query = "select count(*) from Cars where IsBadBuy = 1 and " . $feature . " = '" . $variable . "'";
			endif;
			$result = dbQuery($query, $db);
			$count = $result[0]["count(*)"];
			$pVariableAndKickedArray[$feature][$variable] = ($count + $lambda)/$norm[$feature];
		endif;
	endforeach;
	$pVariableArray[$feature]["nothing"] = (2 * $lambda)/$norm[$feature];
	$pVariableAndKickedArray[$feature]["nothing"] = $lambda/$norm[$feature];
endforeach;

$metaVariableArray = array();
foreach(array_keys($pVariableAndKickedArray) as $feature):
	foreach(array_keys($pVariableAndKickedArray[$feature]) as $variable):
		$metaVariableArray[$feature . " " . $variable] = ($pVariableAndKickedArray[$feature][$variable]/$pVariableArray[$feature][$variable]);
	endforeach;
endforeach;
#var_dump($metaVariableArray);

foreach(array_keys($metaVariableArray) as $key):
	echo $key . "," . $metaVariableArray[$key] . "\n";
endforeach;

#echo "Testing ==============================================\n";
#TESTING
$correct = 0;
$count = 0;
#$pout1 = $pKicked;
#$pout0 = (1-$pKicked);

$testData = fopen("test.csv", "r");
$falsePositive = array();
$falseNegative = array();
while(($line = fgets($testData)) !== false):
	$pout1 = log($pKicked);
	$pout0 = log(1-$pKicked);
	$tokensTemp = split(",", $line);
	$result = $tokensTemp[1];
	$tokens = array_slice($tokensTemp,2);
	$featureIndex = 0;
	#echo "=======================\n";
	foreach($features as $feature):
		$variable = $tokens[$featureIndex];
		$variable = str_replace("\n", "", $variable);
		#echo $variable . " " . (log($pVariableAndKickedArray[$feature][$variable])-log($pKicked)) . "\n";
		if($pVariableAndKickedArray[$feature][$variable]):
			$pout1 += log($pVariableAndKickedArray[$feature][$variable]) - log($pKicked);
			$pout0 += log($pVariableArray[$feature][$variable] - $pVariableAndKickedArray[$feature][$variable]) - log(1-$pKicked); 
		else:
			#echo log($pVariableAndKickedArray[$feature]["nothing"]) - log($pKicked) . "\n";
			$pout1 += log($pVariableAndKickedArray[$feature]["nothing"]) - log($pKicked);
			$pout0 += log($pVariableArray[$feature]["nothing"] - $pVariableAndKickedArray[$feature]["nothing"]);
		endif;
		$featureIndex += 1;
	endforeach;
	$guess = 0;
	#echo $pout1 . "\n";
	#echo $pout0 . "\n";
	if(($pout1 > $pout0)):
		$guess = 1;
	endif;
	#echo $result . $guess . "\n";
	if($result == $guess):
		$correct += 1;
	else:
		if($guess == 0):
			array_push($falseNegative, $tokensTemp[0]);
		else:
			array_push($falsePositive, $tokensTemp[0]);
		endif;
	endif;
	$count += 1;
endwhile;
if(!feof($testData)):
	echo "Unexpected fgets fail\n";
endif;
fclose($testData);
#var_dump($falseNegative);
var_dump($falsePositive);
echo "\n\nPercent Correct " . floatval($correct)/$count . "\n";


?>
