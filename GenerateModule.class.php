<?php
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class GenerateModuleCommand extends Command{
	protected function configure(){
		$this->setName('generate');
		$this->setDescription('Creates a skeleton module for FreePBX 13+');
		$this->setHelp('This command creates a new module in the present working directory');
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		$this->output = $output;
		$this->input = $input;
		$helper = $this->getHelper('question');
		$question = new ChoiceQuestion('Do you want to creat a FreePBX or a UCP module?',
											            array('FreePBX','UCP'),2);
	        $this->moduleType = $helper->ask($input,$output,$question);
		$question = new Question('What is your module\'s name no spaces? ', 'helloworld');
		$question->setNormalizer(function ($value) {
			return strtolower(trim($value));
		});
		$this->rawname  = $helper->ask($input, $output, $question);
                $this->basedir = getcwd().'/'.$this->rawname;
		if(is_dir($this->basedir)){
		    	$output->writeln("<error>This name ({$this->rawname}) is already in use</error>");
			exit(1);
		}
		$question = new Question('What is your module\'s version? ', '13.0.1');
		$this->version =  $helper->ask($input, $output, $question);
		$question = new Question('What is your module\'s description? ', 'Generated Module');
		$this->description = $helper->ask($input, $output, $question);
		$question = new ChoiceQuestion('What is the license for this module?',
											array('GPLv2','GPLv3','AGPLv3','MIT'),2);
		$this->license =  $helper->ask($input, $output, $question);
		$question = new ChoiceQuestion('What type of module is this?',
											array('Admin','Applications','Connectivity','Reports','Settings'),2);
		$this->apptype =  $helper->ask($input, $output, $question);
		$qtext = "Generate a module with the following information?".PHP_EOL;
	        $qtext .= "Module type: ".$this->moduleType.PHP_EOL;
		$qtext .= "Module rawname: " .$this->rawname.PHP_EOL;
		$qtext .= "Module version: " .$this->version.PHP_EOL;
		$qtext .= "Module description: " .$this->description.PHP_EOL;
		$qtext .= "Module type: " .$this->apptype.PHP_EOL;
		$qtext .= "Module License: " .$this->license.PHP_EOL;
		$qtext .= "Type [yes|no]:";
		$question = new ConfirmationQuestion($qtext, false);
		if (!$helper->ask($input, $output, $question)) {
			return;
		}
                
		if($this->moduleType == 'FreePBX'){
			$this->makeFileStructure();
			$this->touchFiles();
			$this->makeModuleXML();
			$this->makeBMO();
			$this->makePage();
			$this->copyLic();
		}
		else{ //UCP Module
			$this->makeFileStructureUCP();
			$this->touchFilesUCP();
			$this->makeModuleXMLUCP();
			$this->makeBMOUCP();
			$this->makeUCPClass();
			$this->makeGlobaljsUCP();
			$this->makelessUCP();
			$this->copyWidget();
			$this->copySettings();	
			$this->copyReadme();
			$this->copyLic();
			$this->runLinker();
			$this->updateSymLinks();
			$this->setPermissions();
			$this->installModule();
			$this->reload();
		}
	}
	protected function makeFileStructure(){
		$this->output->writeln("Generating Directories for your module");
		$fs = new Filesystem();
		$directories = array(
			$this->basedir .'/views',
			$this->basedir .'/assets/css',
			$this->basedir .'/assets/js',
		);
		$fs->mkdir($directories);

	}
	protected function makeFileStructureUCP(){
                $this->output->writeln("Generating Directories for your UCP module");
                $fs = new Filesystem();
                $directories = array(
                        $this->basedir .'/views',
                        $this->basedir .'/ucp/assets/js',
			$this->basedir .'/ucp/assets/less',
                        $this->basedir .'/ucp/views',
                );
                $fs->mkdir($directories);

        }
	protected function touchFiles(){
		$this->output->writeln("Generating File structure for your module");
		$fs = new Filesystem();
		$uppername = ucfirst($this->rawname);
		$files = array(
			$this->basedir .'/'.$uppername.'.class.php',
			$this->basedir .'/install.php',
			$this->basedir .'/uninstall.php',
			$this->basedir .'/module.xml',
			$this->basedir .'/page.'.$this->rawname.'.php',
			$this->basedir .'/assets/css/'.$this->rawname.'.css',
			$this->basedir .'/assets/js/'.$this->rawname.'.js',
			$this->basedir .'/views/main.php'
		);
		$fs->touch($files);
	}
	protected function touchFilesUCP(){
                $this->output->writeln("Generating File structure for your UCP module");
                $fs = new Filesystem();
                $uppername = ucfirst($this->rawname);
                $files = array(
                        $this->basedir .'/'.$uppername.'.class.php',
                        $this->basedir .'/install.php',
                        $this->basedir .'/uninstall.php',
                        $this->basedir .'/module.xml',
			$this->basedir .'/views/ucp_config.php',
			$this->basedir .'/ucp/'.$uppername.'.class.php',
			$this->basedir .'/ucp/assets/js/global.js',
			$this->basedir .'/ucp/assets/less/'.$uppername.'.less',
			$this->basedir .'/ucp/views/user_settings.php',
			$this->basedir .'/ucp/views/'.$this->rawname.'.php',
                );
                $fs->touch($files);
        }
	protected function makeModuleXML(){
		$this->output->writeln("Generating module.xml");

		$data = array(
			'rawname' => $this->rawname,
			'name' => ucfirst($this->rawname),
			'version' => $this->version,
			'publisher' => 'Generated Module',
			'license' => $this->license,
			'changelog' => '*'.$this->version.'* Initial release',
			'category' => $this->apptype,
			'description' => $this->description,
			'menuitems' => array($this->rawname => ucfirst($this->rawname)),
			'supported' => '13.0'
		);
		$xml = new SimpleXMLElement('<module/>');
		foreach ($data as $key => $value) {
			if($key == 'menuitems'){
				$menu = $xml->addChild($key);
				foreach ($value as $k => $v) {
					$menu->addChild($k,$v);
				}
			}else{
				$xml->addChild($key,$value);
			}
		}
		$dom = dom_import_simplexml($xml)->ownerDocument;
		$dom->formatOutput = true;
		$out = $dom->saveXML(); 
		$out = str_replace('<?xml version="1.0"?>', '', $out);
		file_put_contents ( $this->basedir.'/module.xml', $out);
	}
        protected function makeModuleXMLUCP(){
                $this->output->writeln("Generating module.xml");

                $data = array(
                        'rawname' => $this->rawname,
			'repo' => 'standard',
                        'name' => 'UCP '.ucfirst($this->rawname),
			'description' => 'This is an example UCP application. It is meant as a reference tool and may or may not functionally do anything useful',
			'category' => 'Applications',
                        'version' => $this->version,
                        'publisher' => 'Sangoma Technologies Corporation',
                        'license' => $this->license,
			'licenselink' => 'http://www.gnu.org/licenses/gpl-3.0.txt',  //TODO: Put the correct license link
                        'changelog' => '*'.$this->version.'* Initial release',
			'hooks' => array('ucp' => array(
				'constructModuleConfigPages' => 'ucpConfigPage',
				'addUser' => 'ucpAddUser',
				'updateUser' => 'ucpUpdateUser',
			        'delUser' => 'ucpDelUser',
			        'addGroup' => 'ucpAddGroup',
			        'updateGroup' => 'ucpUpdateGroup',
			        'delGroup' => 'ucpDelGroup')),
                        'supported' => array('version' => '14.0'),
                );
                $xml = new SimpleXMLElement('<module/>');
                foreach ($data as $key => $value) {
                        if($key == 'hooks'){
                                $menu = $xml->addChild($key);
                                foreach ($value as $k => $v) {
					$menu2 = $menu->addChild($k);
					$menu2->addAttribute('class','Ucp');
					foreach($v as $k2 => $v2){
					    $submenu = $menu2->addChild('method',$v2);
					    $submenu->addAttribute('namespace','FreePBX\Modules');
					    $submenu->addAttribute('class',ucfirst($this->rawname));
					    $submenu->addAttribute('callingMethod',$k2);
					}
                                }
                        }elseif($key == 'supported'){
				$menu = $xml->addChild($key);
				foreach($value as $k => $v)
				     $menu->addChild($k,$v);
			}else{
                                $xml->addChild($key,$value);
                        }
                }
                $dom = dom_import_simplexml($xml)->ownerDocument;
                $dom->formatOutput = true;
                $out = $dom->saveXML();
                $out = str_replace('<?xml version="1.0"?>', '', $out);
                file_put_contents ( $this->basedir.'/module.xml', $out);
        }
	protected function makeBMO(){
		$this->output->writeln("Generating BMO class");
		$uppername = ucfirst($this->rawname);
		$template = file(__DIR__.'/resources/BMO.template');
		$bmofile =  fopen($this->basedir .'/'.$uppername.'.class.php',"w");
		$find = array('##RAWNAME##','##CLASSNAME##');
		$replace = array($this->rawname, $uppername);
		foreach ($template as $line) {
			$out = str_replace($find, $replace, $line);
			fwrite($bmofile, $out);
		}
		fclose($bmofile);
	}
        protected function makeBMOUCP(){
                $this->output->writeln("Generating UCP BMO class");
                $uppername = ucfirst($this->rawname);
                $template = file(__DIR__.'/resources/BMOUCP.template');
                $bmofile =  fopen($this->basedir .'/'.$uppername.'.class.php',"w");
                $find = array('##RAWNAME##','##CLASSNAME##');
                $replace = array($this->rawname, $uppername);
                foreach ($template as $line) {
                        $out = str_replace($find, $replace, $line);
                        fwrite($bmofile, $out);
                }
                fclose($bmofile);
        }
	protected function makeUCPClass(){
                $this->output->writeln("Generating UCP Module class");
                $uppername = ucfirst($this->rawname);
                $template = file(__DIR__.'/resources/UCPclass.template');
                $Cfile =  fopen($this->basedir .'/ucp/'.$uppername.'.class.php',"w");
                $find = array('##RAWNAME##','##CLASSNAME##');
                $replace = array($this->rawname, $uppername);
                foreach ($template as $line) {
                        $out = str_replace($find, $replace, $line);
                        fwrite($Cfile, $out);
                }
                fclose($Cfile);
        }
	protected function makeGlobaljsUCP(){
                $this->output->writeln("Generating UCP Module Global JS");
                $uppername = ucfirst($this->rawname);
                $template = file(__DIR__.'/resources/globaljs.template');
                $Gfile =  fopen($this->basedir .'/ucp/assets/js/global.js',"w");
                $find = array('##RAWNAME##','##CLASSNAME##');
                $replace = array($this->rawname, $uppername);
                foreach ($template as $line) {
                        $out = str_replace($find, $replace, $line);
                        fwrite($Gfile, $out);
                }
                fclose($Gfile);
        }
	protected function makelessUCP(){
                $this->output->writeln("Generating UCP Module Less file");
                $uppername = ucfirst($this->rawname);
                $template = file(__DIR__.'/resources/less.template');
                $Lfile =  fopen($this->basedir .'/ucp/assets/less/'.$uppername.'.less',"w");
                $find = array('##RAWNAME##','##CLASSNAME##');
                $replace = array($this->rawname, $uppername);
                foreach ($template as $line) {
                        $out = str_replace($find, $replace, $line);
                        fwrite($Lfile, $out);
                }
                fclose($Lfile);
        }
	protected function makePage(){
		$this->output->writeln("Generating module page and view");
		$uppername = ucfirst($this->rawname);
		$template = file(__DIR__.'/resources/page.template');
		$pagefile =  fopen($this->basedir .'/page.'.$this->rawname.'.php',"w");
		$find = array('##RAWNAME##','##CLASSNAME##');
		$replace = array($this->rawname, $uppername);
		foreach ($template as $line) {
			$out = str_replace($find, $replace, $line);
			fwrite($pagefile, $out);
		}
		fclose($pagefile);
		$view = fopen($this->basedir .'/views/main.php',"w");
		fwrite($view, "IT WORKS!!!! Generated for ".$uppername);
		fclose($view);
	}
	protected function copyLic(){
		$fs = new Filesystem();
		$fs->copy(__DIR__.'/resources/'.$this->license, $this->basedir .'/LICENSE');
	}
	protected function copyWidget(){
                $fs = new Filesystem();
                $fs->copy(__DIR__.'/resources/willy.php', $this->basedir .'/ucp/views/willy.php');
        }
	protected function copySettings(){
                $fs = new Filesystem();
                $fs->copy(__DIR__.'/resources/user_settings.php', $this->basedir .'/ucp/views/user_settings.php');
        }
	protected function copyReadme(){
                $fs = new Filesystem();
                $fs->copy(__DIR__.'/resources/README.md', $this->basedir .'/README.md');
        }
	protected function runLinker(){
		$this->output->writeln("Linking your module folder into the framework module folder");
		$process = new Process('/usr/src/devtools/freepbx_git.php -s');
		$process->run(function($type, $buffer) {
                    if (Process::ERR === $type) {
			$this->output->writeln($buffer);
                    } else
                        $this->output->writeln($buffer);
                });
	}
	protected function updateSymLinks(){
		$this->output->writeln("Framework updating the symlinks");
                $process = new Process('/usr/src/freepbx/framework/install --dev-links -n');
		$process->run(function($type, $buffer) {
                    if (Process::ERR === $type) {
			$this->output->writeln($buffer);
                    } else 
                        $this->output->writeln($buffer);
                });
	}
	protected function setPermissions(){
		$this->output->writeln("Setting proper permissions to your module");
                $process = new Process('chown -R asterisk:asterisk /usr/src/freepbx');
                $process->run();
		if (!$process->isSuccessful()) {
 		   throw new ProcessFailedException($process);
		   exit(1);
		}
	}
	protected function installModule(){
                $this->output->writeln("Installing your module");
                $process = new Process("fwconsole ma install ".escapeshellarg($this->rawname));
		$process->run(function($type, $buffer) {
                    if (Process::ERR === $type) {
			$this->output->writeln($buffer);
                    } else 
                        $this->output->writeln($buffer);
                });
        }
	protected function reload(){
                $this->output->writeln("Reloading");
                $process = new Process("fwconsole reload");
		$process->run(function($type, $buffer) {
                    if (Process::ERR === $type) {
			$this->output->writeln($buffer);
                    } else
                        $this->output->writeln($buffer);
                });
        }
}
