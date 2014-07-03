<?php

namespace image\exception {
	class InvalidDataException extends \InvalidArgumentException {}
	class InvalidFileException extends \InvalidArgumentException {}
	class GDMethodFailed extends \RuntimeException {}
}

namespace image\compare {
	class InvalidOperationException extends \UnexpectedValueException {}
}

namespace image\signature {
	class Store {
		public $signature;
		public $customData;
		
		public function __construct($signature = null,$customData = null){
			if($signature !== null){
				$this->signature = $signature;
			}
			if($customData !== null){
				$this->customData = $customData;
			}
		}
	}
}

namespace image {
	
	abstract class Bitwise {
		public static function IsBitInValue($bit,$value){
			return ($value & $bit) != 0;
		}
	}
	
	abstract class Compare {
		const EqualTo = 1;
		const GreaterThan = 2;
		const LessThan = 4;
		const CondAnd = 8;
		const CondOr = 16;
		
		public static function FormatArguments($arguments){
			if(count($arguments) ==  2){
				$arguments = array_splice($arguments,1,0,self::EqualTo);
			}
			if(count($arguments) != 3){
				throw new \InvalidArgumentException('Expected three arguments.');
			}
			$arguments[1] = self::FormatOperator($arguments[1]);
			return $arguments;
		}
		
		public static function FormatOperator($operator){
			if(!Bitwise::IsBitInValue(
				self::EqualTo|self::GreaterThan|self::LessThan,
				$operator
			)){
				$operator = $operator|self::EqualTo;
			}
			if(!Bitwise::IsBitInValue(
				self::CondOr|self::CondAnd,
				$operator
			)){
				$operator = $operator|self::CondAnd;
			}
			return $operator;
		}
		
		public static function CompareValues(){
			list($a,$operator,$b) = self::FormatArguments(func_get_args());
			$equalTo = Bitwise::IsBitInValue(self::EqualTo,$operator);
			if(Bitwise::IsBitInValue(self::GreaterThan,$operator)){
				return $equalTo ? $a >= $b : $a > $b;
			}
			else if(Bitwise::IsBitInValue(self::LessThan,$operator)){
				return $equalTo ? $a <= $b : $a < $b;
			}
			else if($equalTo){
				return $a == $b;
			}
			else {
				throw new compare\InvalidOperationException;
			}
		}
		
		public static function CompareValuesIf(){
			$arguments = func_get_args();
			$conditional = array_shift($arguments);
			$arguments = self::FormatArguments($arguments);
			$result = call_user_func_array(array(__CLASS__,'CompareValues'),$arguments);
			if(Bitwise::IsBitInValue(self::CondOr,$arguments[1])){
				return $conditional || $result;
			}
			else if(Bitwise::IsBitInValue(self::CondAnd,$arguments[1])){
				return $conditional && $result;
			}
			else {
				throw new compare\InvalidOperationException;
			}
		}
	}
	
	class Component {
		public function __get($name){
			$method = 'get'.ucfirst($name);
			if(method_exists($this,$method)){
				return $this->$method();
			}
			$trace = debug_backtrace();
			trigger_error(
				'Undefined property via __get(): ' . $name .
				' in ' . $trace[0]['file'] .
				' on line ' . $trace[0]['line'],
				E_USER_NOTICE
			);
			return null;
		}
		public function __set($name,$value){
			$method = 'set'.ucfirst($name);
			if(method_exists($this,$method)){
				return $this->$method($value);
			}
			$trace = debug_backtrace();
			trigger_error(
				'Undefined property via __set(): ' . $name .
				' in ' . $trace[0]['file'] .
				' on line ' . $trace[0]['line'],
				E_USER_NOTICE
			);
			return null;
		}
	}
	
	class Rect extends Component {
		public $x, $y, $width, $height = 0;
		
		public function __construct($x = 0,$y = 0,$width = 0,$height = 0){
			$this->x = $x;
			$this->y = $y;
			$this->width = $width;
			$this->height = $height;
		}
		
		const X = 1;
		const Y = 2;
		const Width = 4;
		const Height = 8;
		
		public function compareTo(Rect $rect,$parameters = null,$operator = Compare::EqualTo){
			if($parameters == null){
				$parameters = self::X | self::Y | self::Width | self::Height;
			}
			$result = true;
			if(Bitwise::IsBitInValue(self::X,$parameters)){
				$result = Compare::CompareValuesIf($result,$this->x,$operator^Compare::CondOr,$rect->x);
			}
			if(Bitwise::IsBitInValue(self::Y,$parameters)){
				$result = Compare::CompareValuesIf($result,$this->y,$operator,$rect->y);
			}
			if(Bitwise::IsBitInValue(self::Width,$parameters)){
				$result = Compare::CompareValuesIf($result,$this->width,$operator,$rect->width);
			}
			if(Bitwise::IsBitInValue(self::Height,$parameters)){
				$result = Compare::CompareValuesIf($result,$this->height,$operator,$rect->height);
			}
			return $result;
		}
		
		public function getAspectRatio(){
			return $this->width / $this->height;
		}
		
		
		const CENTER = 1;
		const NO_ENLARGE = 2;
		public function fitInsideOf(Rect $rect,$flags = 0){
			$ratio = $this->aspectRatio;
			$x = $this->x;
			$y = $this->y;
			$width = null;
			$height = null;
			if(Bitwise::IsBitInValue(self::NO_ENLARGE,$flags)){
				if($rect->width >= $this->width && $rect->height >= $this->height){
					$width = $this->width;
					$height = $this->height;
				}
			}
			if($width === null && $height === null){
				$width = $rect->width;
				$height = $width / $ratio;
				if($height > $rect->height){
					$height = $rect->height;
					$width = $height * $ratio;
				}
			}
			if(Bitwise::IsBitInValue(self::CENTER,$flags)){
				if($height < $rect->height){
					$y = ($rect->height - $height) / 2;
				}
				else if($width < $rect->width){
					$x = ($rect->width - $width) / 2;
				}
			}
			return new Rect($x,$y,$width,$height);
		}
		
		public function printBlock($color){
			echo sprintf(
				'<div style="position:absolute;top:%spx;left:%spx;width:%spx;height:%spx;background:%s"></div>',
				$this->y,
				$this->x,
				$this->width,
				$this->height,
				$color
			);
		}
	}
	
	class CopyData {
		public $image, $rect;
		
		public function __construct(Image $image,Rect $rect){
			$this->image = $image;
			$this->rect = $rect;
		}
	}
	
	class SignatureStore extends Component {
		protected $_signatures = array();
		
		public function getSignatures(){
			return $this->_signatures;
		}
		
		public function findSimilarImage(Image $image,$customData = null,$tolerence = .6){
			$imageSignature = $image->signature;
			foreach($this->_signatures as $signature){
				if(puzzle_vector_normalized_distance($imageSignature,$signature->signature) < $tolerence){
					return $signature->customData;
				}
			}
			$this->_signatures[] = new signature\Store($imageSignature,$customData);
			return $customData;
		}
	}
	
	class File extends Component {
		
		protected static $_fileSignatures = null;
		protected $_resource = null;
		public $localUrl = null;
		public $remoteUrl = null;
		protected $_info = null;
		
		
		public function __construct($resource,$remoteUrl = null){
			$this->_resource = $resource;
			$info = stream_get_meta_data($resource);
			$this->localUrl = $info['uri'];
			$this->remoteUrl = $remoteUrl;
			$this->_info = pathinfo($remoteUrl);
		}
		
		public static function getFileSignatures(){
			if(!self::$_fileSignatures){
				self::$_fileSignatures = array(
					'gif' => chr(0x47).chr(0x49).chr(0x46).chr(0x38).chr(0x39).chr(0x61),
					'png' => chr(0x89).chr(0x50).chr(0x4E).chr(0x47),
					'jpg' => chr(0xFF).chr(0xD8)
				);
			}
			return self::$_fileSignatures;
		}
		
		public static function CreateFromUrl($url){
			if(file_exists($url)){
				return new File(fopen($url,'r'),$url);
			}
			$temp = tmpfile();
			$remote = fopen($url,'r');
			while(!feof($remote)){
				fwrite($temp,fread($remote,1024 * 2500));
			}
			fclose($remote);
			rewind($temp);
			return new File($temp,$url);
		}
		
		public function getType(){
			$header = fread($this->_resource,10);
			rewind($this->_resource);
			foreach(self::getFileSignatures() as $type => $signature){
				if(strpos($header,$signature) === 0){
					return $type;
				}
			}
			return $this->getTypeFromExtension();
		}
		
		public function getTypeFromExtension(){
			$extension = $this->extension;
			if($extension == 'jpg' || $extension == 'jpeg'){
				return 'jpg';
			}
			else if($extension == 'png'){
				return 'png';
			}
			else if($extension == 'gif'){
				return 'gif';
			}
		}
		
		public function getExtension(){
			return $this->_info['extension'];
		}
		
		public function __destruct(){
			fclose($this->_resource);
		}
	}
	
	class Image extends Component {
		protected $_resource, $_signature;
	
		protected function __construct($resource){
			$this->_resource = $resource;
		}
		
		// Constructors
		
		public static function CreateFromUrl($url){
			return self::CreateFromFile(File::CreateFromUrl($url));
		}
		
		public static function CreateFromFile($file){
			$type = $file->type;
			if($type == 'jpg'){
				$resource = @imagecreatefromjpeg($file->localUrl);
			}
			else if($type == 'png'){
				$resource = @imagecreatefrompng($file->localUrl);
			}
			else if($type == 'gif'){
				$resource = @imagecreatefromgif($file->localUrl);
			}
			if($resource){
				return new Image($resource);
			}
			else {
				try {
					return Image::CreateFromData(file_get_contents($file->localUrl));
				}
				catch(exception\InvalidDataException $e){
					throw new exception\InvalidFileException;
				}
			}
		}
	
		public static function CreateFromData($data){
			$resource = @imagecreatefromstring($data);
			if(!$resource){
				throw new exception\InvalidDataException;
			}
			return new Image($resource);
		}
		
		public static function CreateFromStream($stream){
			return self::CreateFromFile(new File($stream));
		}
	
		public static function CreateNew($width,$height){
			return new Image(imagecreatetruecolor($width,$height));
		}
		
		// Methods
		
		public function getSignature(){
			if(!$this->_signature){
				$tmpFile = tempnam(sys_get_temp_dir(),'signatureImage');
				$this->renderPng(9,$tmpFile);
				$this->_signature = puzzle_fill_cvec_from_file($tmpFile);
				unlink($tmpFile);
			}
			return $this->_signature;
		}
		
		public function copy(Rect $sourceRect = null){
			if($sourceRect == null){
				$sourceRect = $this->rect;
			}
			return new CopyData($this,$sourceRect);
		}
		
		public function paste(CopyData $copyData,Rect $destRect = null){
			$dest = $this->resource;
			$destRect = $destRect ?: $this->rect;
			$source = $copyData->image->resource;
			$sourceRect = $copyData->rect;
			$result = null;
			if($destRect->compareTo($sourceRect,Rect::Width|Rect::Height)){
				$result = imagecopy(
					$dest,
					$source,
					$destRect->x,
					$destRect->y,
					$sourceRect->x,
					$sourceRect->y,
					$destRect->width,
					$destRect->height
				);
				if(!$result){
					throw new exception\GDMethodFailed('imagecopy');
				}
				else return $this;
			}
			else {
				$result = imagecopyresampled(
					$dest,
					$source,
					$destRect->x,
					$destRect->y,
					$sourceRect->x,
					$sourceRect->y,
					$destRect->width,
					$destRect->height,
					$sourceRect->width,
					$sourceRect->height
				);
				if(!$result){
					throw new exception\GDMethodFailed('imagecopyresampled');
				}
				else return $this;
			}
		}
		
		public function crop(Rect $sourceRect){
			$newImage = Image::CreateNew($sourceRect->width,$sourceRect->height);
			return $newImage->paste($this->copy($sourceRect));
		}
		
		public function resize(Rect $destRect){
			$newImage = Image::CreateNew($destRect->width,$destRect->height);
			return $newImage->paste($this->copy());
		}
		
		public function duplicate(){
			$newImage = Image::CreateNew($this->width,$this->height);
			$newImage->paste($this->copy());
			return $newImage;
		}
		
		public function retainAlpha(){
			imagealphablending($this->resource,true);
			imagesavealpha($this->resource,true);
			//$overlay = imagecolorallocatealpha($this->resource,220,220,220,127);
			//imagefill($this->resource,0,0,$overlay);
		}
		
		public function renderPng($quality = 0,$path = null,$filters = PNG_NO_FILTER){
			if($path == null){
				ob_start();
			}
			$result = imagepng($this->resource,$path,$quality,$filters);
			if(!$result){
				throw new exception\GDMethodFailed('imagepng');
			}
			if($path == null){
				return ob_get_clean();
			}
		}
		
		public function renderJpeg($quality = 7,$path = null){
			$quality = ($quality / 9) * 100;
			if($path == null){
				ob_start();
			}
			$result = imagejpeg($this->resource,$path,$quality);
			if(!$result){
				throw new exception\GDMethodFailed('imagejpeg');
			}
			if($path == null){
				return ob_get_clean();
			}
		}
		
		// Accessor methods
		public function getResource(){
			return $this->_resource;
		}
		
		public function getRect($x = 0,$y = 0){
			return new Rect($x,$y,$this->width,$this->height);
		}
		
		public function getWidth(){
			return imagesx($this->resource);
		}
		
		public function getHeight(){
			return imagesy($this->resource);
		}
		
		public function setWidth($width){
			return imagesx($this->resource);
		}
		
		public function setHeight($height){
			return imagesy($this->resource);
		}
		
		public function getArea(){
			return $this->width * $this->height;
		}
		
		public function __destruct(){
			imagedestroy($this->resource);
		}
	}
}
/*
namespace {
	header('Content-Type: image/png');
	$destinationRect = new image\Rect(0,0,500,500);
	$image = image\Image::CreateFromUrl(dirname(__FILE__).'/TestImage.png');
	$image = $image->crop(
		$destinationRect->constrainTo($image->rect,image\Rect::CENTER)
	)->resize($destinationRect)->renderPng(9);
}*/
?>
