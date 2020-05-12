<?php
/* 
Outil pour lister quelle sections sont modifiees et par quoi dans Magento 1
*/
include_once("config.php");


global $Gmethodes;
$folder = __DIR__;
if (file_exists("info.ini")){
	$options = parse_ini_file("info.ini");
	$folder = $options["folder"];
	$Greferences = json_decode($options["references"],true);
	$Gmethodes = json_decode($options["methodes"],true);
}else{
	$Greferences = [];
	$Gmethodes = [];
	$options = ["references"=>json_encode($Greferences)];
	$options = ["methodes"=>json_encode($GMethods)];
}

if (isset($_POST["folder"])){
	$folder = $_POST["folder"];
}
$folder = str_replace("\\","/",$folder);
	
$bCheck = false;
$tabModules = [];
$tabOverrides = [];
if (is_dir($folder."/app")){
	//Parcourt des reps
	$bCheck = true;
	$tabOverrideInfo = ["blocks","helpers","models","resources","events"];
								
	//Liste des elemnts deprecies
	$tabDeprecated = [];
	$packages = scandir($folder."/app/code");
	foreach ($packages as $package){
		if ($package != "." and $package != ".."){
			$providers = scandir($folder."/app/code/".$package);
			foreach ($providers as $provider){
				if ($provider != "." and $provider != ".."){
					$modules = scandir($folder."/app/code/".$package."/".$provider);
					foreach ($modules as $module){
						if ($module != "." and $module != ".."){
							$file_config = $folder."/app/code/".$package."/".$provider."/".$module."/etc/config.xml";
							if (file_exists($file_config)){
								$xml = simplexml_load_file($file_config);
								$xml = xmlToArray($xml);
								
								foreach ($tabOverrideInfo as $override){
									if (isset($xml["config"]["global"][$override])){
										foreach ($xml["config"]["global"][$override] as $keynode=>$node){
											if (isset($node["deprecatedNode"]) and isset($node["class"])){
												$tabDeprecated[$provider."_".$node["deprecatedNode"]["value"]] = $node["class"]["value"];
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}
	
	$iNbFailed = 0;
	$packages = scandir($folder."/app/code");
	foreach ($packages as $package){
		if ($package != "." and $package != ".."){
			$providers = scandir($folder."/app/code/".$package);
			foreach ($providers as $provider){
				if ($provider != "." and $provider != ".."){
					$modules = scandir($folder."/app/code/".$package."/".$provider);
					foreach ($modules as $module){
						if ($module != "." and $module != ".."){
							$file_config = $folder."/app/code/".$package."/".$provider."/".$module."/etc/config.xml";
							if (file_exists($file_config)){
								//echo $file_config."<br/>";
								$xml = simplexml_load_file($file_config);
								$xml = xmlToArray($xml);
								
								foreach ($tabOverrideInfo as $override){
									if (isset($xml["config"]["global"][$override])){
										foreach ($xml["config"]["global"][$override] as $keynode=>$node){
											if (isset($node["rewrite"])){
												foreach ($node["rewrite"] as $rewrite=>$value){
													
													$link = "";
													$failed = "color:red;";
													foreach ($packages as $spackage){
														if ($spackage != "." and $spackage != ".."){
															$devs = array_merge([""],scandir($folder."/app/code/".$spackage));
															foreach ($devs as $dev){
																if ($failed != "" and $dev != ".." and $dev != "."){
																	if ($dev != ""){
																		$dev = $dev ."_";
																	}
																	
																	$tabKeyNode = explode("_",$keynode);
																	
																	
																	$classes = explode("_",$dev.$tabKeyNode[0]."_".substr($override,0,strlen($override)-1));
																	if (isset($tabKeyNode[1])){
																		$z = 0;
																		foreach ($tabKeyNode as $keynodex){
																			if ($z>0){
																				$classes[] = $keynodex;
																			}
																			$z++;
																		}
																		
																	}
																	
																	$classes[] = $rewrite;
																	
																	
																	$classe = implode("_",$classes);
																	
																	$file_check = $folder."/app/code/".$spackage."/".str_replace("_","/",$classe).".php";
																	if (file_exists($file_check)){
																		$failed = "";
																		$link = "pheditor.php?sub=". dirname($file_check)."&file=/". basename($file_check)."#/". basename($file_check);
																	}else{
																		//Il y a 2 mode d ecriture, on test les 2 modes
																		$classes2 = explode("_",$dev.$keynode."_".substr($override,0,strlen($override)-1));
																		
																		$classes2[] = $rewrite;
																		$classe = implode("_",$classes2);
																		
																		$file_check = $folder."/app/code/".$spackage."/".str_replace("_","/",$classe).".php";
																		if (file_exists($file_check)){
																			$failed = "";
																			$link = "pheditor.php?sub=". dirname($file_check)."&file=/". basename($file_check)."#/". basename($file_check);
																		}
																	}
																}
															}
														}
													}
													
													//Si erreur
													if ($failed != ""){
														$iNbFailed++;
														//On remplace le dernier paquet analysé par celui par defaut (mage)
														$classe = explode("_",$classe);
														$classe[0] = "Mage";
														$classe = implode("_",$classe);
														
														//On remplace les paquets dépréciés
														$deprecated = false;
														foreach ($tabDeprecated as $key=>$v){
															if (stripos($classe,$key) !== false){
																$classe = str_ireplace($key,$v,$classe);
																$failed .= "text-decoration:line-through;";
																$deprecated = true;
															}
														}
														
														if ($deprecated){
															$iNbFailed--;
														}
													}
													
													//On verifie si il faut lier ces 2 mots
													if (stripos($classe,"_resource") !== false and stripos($classe,"_model") !== false){
														$classe = str_ireplace("_model","",$classe);
														$classe = str_ireplace("_resource","_Model_Resource",$classe);
													}
													
													
													$classe = camelCase($classe);
													$tabOverrides[$value["value"]] = ["classe"=>$classe,"warning"=>"","type"=>$override,"package"=>$package,"link"=>"<a target='_blank' href='".$link."' style='".$failed."'>".$classe."</a>"];	
												}
											}
										}
									}	
								}
							}
						}
					}
				}
			}
			
		}
	}
	
	foreach ($tabOverrides as $key=>$value){
		foreach ($tabOverrides as $key2=>$value2){
			if ($value["classe"] == $value2["classe"] and $key!=$key2){
				$tabOverrides[$key]["warning"] = "<i class='fa fa-warning' title='Attention surcharge multiple'></i>&nbsp;";//$tabOverrides[$key]["classe"];	
			}
		}
	}
	
	//echo var_dump($tabOverrides);
	//exit();
	if (file_exists($folder."/app/design")){
		$designs = scandir($folder."/app/design");
		foreach ($designs as $design){
			if ($design != "." and $design != ".."){
				$themes = scandir($folder."/app/design/".$design."/");
				foreach ($themes as $theme){
					if ($theme != ".." and $theme != "."){
						
						$subthemes = scandir($folder."/app/design/".$design."/".$theme);
						foreach ($subthemes as $subtheme){
							if ($subtheme != ".." and $subtheme != "."){
								$layoutFolder = $folder."/app/design/".$design."/".$theme."/".$subtheme."/layout";
								$layoutFolderOptim = "/app/design/".$design."/".$theme."/".$subtheme."/layout";
								
								if (file_exists($layoutFolder)){
									$files = scandir($layoutFolder);
									foreach ($files as $file){
										if (is_dir($layoutFolder."/".$file)){
											$subFolder = $layoutFolder."/".$file;
											$subFiles = scandir($subFolder);
											foreach ($subFiles as $subFile){
												if (stripos($subFile,".xml") !== false){
													$xml = simplexml_load_file($subFolder.'/'.$subFile);
													$xml = xmlToArray($xml);
													
													if (isset($xml["layout"])){
														foreach ($xml["layout"] as $nodeName => $nodeValue){
															if ($nodeName != "attributes") {
																if (!isset($tabModules[$nodeName])){
																	$tabModules[$nodeName] = [];
																}
																
																$tabModules[$nodeName][$subFolder."/".$subFile] = $nodeValue;
															}
														}
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
		ksort($tabModules);
	}	
	//echo var_dump($tabModules);exit();
}


/**
* @param SimpleXMLElement $xml
* @return array
*/
function xmlToArray(SimpleXMLElement $xml): array
{
    $parser = function (SimpleXMLElement $xml, array $collection = []) use (&$parser) {
        $nodes = $xml->children();
        $attributes = $xml->attributes();

        if (0 !== count($attributes)) {
            foreach ($attributes as $attrName => $attrValue) {
                $collection['attributes'][$attrName] = strval($attrValue);
            }
        }

        if (0 === $nodes->count()) {
            $collection['value'] = strval($xml);
            return $collection;
        }

        foreach ($nodes as $nodeName => $nodeValue) {
            if (count($nodeValue->xpath('../' . $nodeName)) < 2) {
                $collection[$nodeName] = $parser($nodeValue);
                continue;
            }

            $collection[$nodeName][] = $parser($nodeValue);
        }

        return $collection;
    };

    return [
        $xml->getName() => $parser($xml)
    ];
}
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Explorateur de projets Magento 1</title>

        <!-- Latest compiled and minified CSS -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

		<!-- Latest compiled and minified JavaScript -->
		<script
		  src="https://code.jquery.com/jquery-3.5.0.min.js"
		  integrity="sha256-xNzN2a4ltkB44Mc/Jz3pT4iU1cmeR0FkXs4pru/JxaQ="
		  crossorigin="anonymous"></script>
		
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
		
		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.20/css/jquery.dataTables.css" />
		<link rel="stylesheet" type="text/css" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" />
  
		<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.js"></script>
    </head>
    <body>
        <div class="container" style="max-width:2100px">
			<form method="post">
				<div class="row" style="padding:10px">
					<div class="col-md-6">
						Emplacement du projet Magento
						&nbsp;
						<input name="folder" style="min-width:350px" type="text" value="<?php echo $folder;?>" />
						<input class="btn btn-primary"  type="submit" value="Charger" />
					</div>
				</div>
			</form>
			<br/>
			
			<ul class="nav nav-tabs" id="nav-tab" role="tablist">
			  <li class="nav-item">
				<a class="nav-link" data-toggle="tab" href="#nav-layout">Layout</a>
			  </li>
			  <li class="nav-item">
				<a class="nav-link active" data-toggle="tab" href="#nav-override">Override</a>
			  </li>
			   <li class="nav-item">
				<a class="nav-link" data-toggle="tab" href="#nav-layoutcreator">Layout creator</a>
			  </li>
			</ul>
			
			<div class="tab-content" id="nav-tabContent">
				<div class="tab-pane fade show active" id="nav-override" role="tabpanel" >
					<table id="table_override" class="table table-striped">
						<thead>
							<tr>
								<th>Classe</th><th>Package</th><th>Type</th><th>Surcharge (<?php echo $iNbFailed;?> kc)</th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ($tabOverrides as $classe=>$surcharge){
								?>
								<tr>
									<td><?php echo $classe;?></td>
									<td><?php echo $surcharge["package"];?></td>
									<td><?php echo $surcharge["type"];?></td>
									<td><?php echo $surcharge["warning"].$surcharge["link"];?></td>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
				</div>
				
				<div class="tab-pane fade" id="nav-layout" role="tabpanel" >
					<?php
					if ($bCheck){
					?>
						<table id="table_layout" class="table table-striped">
							<thead>
								<tr>
									<th>Module</th><th>Fichiers</th><th>Références / Templates</th>
								</tr>
							</thead>
							<tbody>
							<?php
							if ($bCheck){
								//echo var_dump($tabModules);exit();
								foreach ($tabModules as $module => $files){
									if ($module != "cXXatalog_product_view"){
										
									?>
									<tr>
										<td>
											<?php echo $module;?>
										</td>
										<td>
											<ul>
											<?php 
											//echo var_dump($detail);exit();
											foreach ($files as $file => $info){
												$file = str_replace("./","",$file);
												?><li><a target="_blank" href='pheditor.php?sub=<?php echo dirname($file);?>&file=/<?php echo basename($file);?>#/<?php echo basename($file);?>'><?php echo str_replace($folder,"",$file);?></a></li>
											<?php
											}
											?>
											</ul>
										</td>
										<td>
											
											<?php 
											//echo var_dump($files);
											foreach ($files as $file => $infos){
												$file = str_replace("./","",$file);
												if (isset($infos["reference"])){

													if (!isset($infos["reference"]["name"])){
														$infos["reference"] = [$infos["reference"]];
													}
													
													foreach ($infos["reference"] as $reference){
														if (isset($reference["attributes"]["name"])){
															?>
															<ul>
																<li>
																	<a href='pheditor.php?sub=<?php echo dirname($file);?>&file=/<?php echo basename($file);?>#/<?php echo basename($file);?>' title="<?php echo str_replace($folder,"",$file);?>">
																		<?php 
																		//echo var_dump($infos);
																		echo $reference["attributes"]["name"];
																		$Greferences[$reference["attributes"]["name"]] = $reference["attributes"]["name"];
																		?>
																	</a>
																	<?php
																	//Recherche des blocks
																	getBlocks($reference,dirname($file));
																	//Recherche des actions
																	getActions($reference,dirname($file));
																	
																	?>
																</li>
															</ul>
															<?php
														}
													}
												}
											}
											?>
											
										</td>
									</tr>
									<?php
									}
								}
							}
							?>
							</tbody>
						</table>
					<?php
					}
					?>
				</div>
				
				<?php
				if ($Greferences != null){
					ksort($Greferences);
				}
				?>
				
				<div class="tab-pane fade" id="nav-layoutcreator" role="tabpanel" >
					<div class="row" style="padding:10px">
					<h3>Créateur de Layout</h3>
					<div class="col-md-6">
						Controlleur: <input type="text" placeholder="controlleur" id="controlleur" name="controlleur" value="" onKeyUp="writeLayout()"/>
						
						<br/>
						Module: <input type="text" placeholder="module" id="module" name="module" value=""  onKeyUp="writeLayout()"/>
						<br/>
						
						Réference:
						<select id="reference" name="reference" onchange="writeLayout()">
							<option></option>
							<?php
							foreach ($Greferences as $ref){
								?>
								<option><?php echo $ref;?></option>
							<?php
							}
							?>
						</select>
						<br/>
						Action Methode: <select id="action" onchange="writeLayout()">
							<option></option>
							<?php
							//$Gmethodes = ["addJs","addItem","addItemAfter","addAttribute","removeItem","setTemplate","setHeaderTitle","setBlockId","setAttributeCode"];
							sort($Gmethodes);
							foreach ($Gmethodes as $method){
								?>
								<option><?php echo $method;?></option>
								<?php
							}
							?>
						</select>
					</div>
					<div class="col-md-6">
						<textarea id="layout" cols="60" rows="10" ></textarea>
					</div>
				</div>
				</div>
			</div>
			
			<script>
			$(document).ready( function () {
				$('.table').DataTable({
					"paging": false,
					"search": {
						"search": ""
					}
				} );
			} );
			
			function writeLayout(){
				$("#layout").html("<"+$("#controlleur").val() +" module=\""+$("#module").val() +"\">");
				$("#layout").html($("#layout").html() + ("\n\t<reference name=\""+$("#reference").val()+"\">"));
				$("#layout").html($("#layout").html() + ("\n\t\t<action method=\""+$("#action").val()+"\">"));
				$("#layout").html($("#layout").html() + ("\n\t\t</action>"));
				$("#layout").html($("#layout").html() + ("\n\t</reference>"));
				$("#layout").html($("#layout").html() + ("\n</"+$("#controlleur").val() +">"));
			}
			</script>
		</div>
    </body>
</html>

<?php
//ecriture du fichier ini
$options["folder"] = $folder;
$options["references"] = json_encode($Greferences);
$options["methodes"] = json_encode($Gmethodes);
write_php_ini($options,"info.ini");


function getBlocks($infos, $dir){
	//echo var_dump(($infos));
	if (isset($infos["block"])){
		if (isset($infos["block"]["attributes"])){
			$infos["block"] = [$infos["block"]];
		}
		foreach ($infos["block"] as $block){
			?>
			<ul>
				<?php
					if (isset($block["attributes"]["template"])){
						?>
						<li>
							<a target="_blank" href='pheditor.php?sub=<?php echo dirname(dirname($dir))."/template/".dirname($block["attributes"]["template"]);?>&file=/<?php echo basename($block["attributes"]["template"]);?>#/<?php echo basename($block["attributes"]["template"]);?>'>
								Block: <?php echo $block["attributes"]["template"];?>
							</a>
						</li>
						<?php
						getActions($block,$dir);
					}
				
				?>
			</ul>
			<?php
		}
	}
}

function getActions($infos, $dir){
	//echo var_dump(($infos));
	global $Gmethodes;
	if (isset($infos["action"])){
		if (isset($infos["action"]["attributes"])){
			$infos["action"] = [$infos["action"]];
		}
		foreach ($infos["action"] as $action){
			?>
			<ul>
				<?php
					if (isset($action["attributes"]["method"])){
						$Gmethodes[$action["attributes"]["method"]] = $action["attributes"]["method"];
					}
					if (isset($action["template"]["value"])){
						?>
						<li>
							<?php
							if (file_exists(dirname(($dir))."/template/".dirname($action["template"]["value"]))."/".basename($action["template"]["value"])){
								$fold = dirname(($dir))."/template/".dirname($action["template"]["value"])."/".basename($action["template"]["value"]);
							}
							if (file_exists(dirname(dirname($dir))."/template/".dirname($action["template"]["value"]))."/".basename($action["template"]["value"])){
								$fold = dirname(dirname($dir))."/template/".dirname($action["template"]["value"])."/".basename($action["template"]["value"]);
							}
							?>
							<a target="_blank" href='pheditor.php?sub=<?php echo $fold;?>&file=/<?php echo basename($action["template"]["value"]);?>#/<?php echo basename($action["template"]["value"]);?>'>
								Action: <?php echo $action["template"]["value"];?>
							</a>
						</li>
						<?php
						
					}
				
				?>
			</ul>
			<?php
		}
	}
}

function camelCase($s){
	$tab = explode("_",$s);
	foreach ($tab as $key=>$value){
		$tab[$key] = ucwords($value);
	}
	return implode("_",$tab);
}
?>

