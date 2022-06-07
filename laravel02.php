<?php
   namespace App\Http\Controllers\Tool;

   use App\Http\Controllers\Controller;
   use App\Http\Controllers\Helper\Currency;
   use App\Models\Manufacturers;
   use App\Models\Product\Category;
   use App\Models\Product\Product;
   use DOMDocument;
   use DOMXPath;
   use Illuminate\Support\Facades\DB;
   use Illuminate\Support\Facades\File;
   use Illuminate\Support\Facades\Http;
   use Illuminate\Support\Facades\Log;
   use Illuminate\Support\Str;
   use Intervention\Image\Facades\Image;

   header( "Cache-Control: no-cache, must-revalidate" );

	  /**
	   * Import
	   *
	   * @url
	   */
   class ProductImport extends Controller {
	  private $productsXPATH           = '//div[@class="text-center well well-white sidebar-nav span4 item"]';
	  private $descriptXPATH           = '//div[@id="leiras"]';
	  private $cookieFile              = 'cookie_jar.txt';
	  private $products_xml_file       = 'Products.xml';
	  private $products_price_xml_file = 'ProductsPrice.xml';
	  private $resourceUrl            = 'https://example.com/';
	  private $loginUrl                = 'https://example.com/login';
	  private $logoutUrl               = 'https://example.com/logout';
	  private $username                = '';
	  private $password                = '';
	  private $getPrice                = false;
	  private $ch;
	  private $login;
	  private $manufacturers           = [
		  'Atman' ,
		  //.........
	  ];

	  public function priceImport()
	  {

		 $this->getPrice = true;
		 $this->ch       = $this->login();
		 $updateProduct = [];
		 $edited = 0;
		 $error  = 0;
		 foreach( $this->getCategories() as $category ) {
			for( $i = 1 ; $i < 6 ; $i++ ) {
			   if( $items = $this->getXpathProducts( $category[ 'url' ] . '/' . $i , $this->ch ) ) {
				  foreach( $items as $item ) {
					 print_r( '.' );
					 try {

						$model = '10-' . explode( '/' , $item->childNodes[ 1 ]->getAttribute( 'href' ) )[ 1 ];
						$product = Product::where( 'model' , $model )->first();
						if( $product ) {
						   $beszer_ar                     = explode( ' ' , $item->childNodes[ 6 ]->nodeValue )[ 1 ];
						   $updateProduct[ $product->id ] = [
							   'beszer_ar' => $beszer_ar ,
							   'price'     => $product->sajat_ar ? $product->price : Currency::getEladasiAr( $beszer_ar )
						   ];
						   $edited++;
						}

					 } catch( \Exception $e ) {
						$error++;
					 }
				  }
			   }
			}
		 }
		 if( File::exists( public_path( $this->cookieFile ) ) ) {
			File::delete( public_path( $this->cookieFile ) );
		 }
		 $this->logout();
		 foreach( $updateProduct as $product_id => $data ) {
			Product::where( 'id' , $product_id )->update( $data );
		 }
		 Log::channel( 'log' )->info( 'Árfrissítés :' . $edited . 'db' );

	  }

	  public function productsImport()
	  {

		 $this->getPrice = false;
		 $this->ch = curl_init();
		 $added         = 0;
		 $edited        = 0;
		 $error         = 0;
		 $insertProduct = [];
		 $updateProduct = [];
		 Product::where( 'model' , 'like' , '%10-%' )->where( 'quantity' , '>' , 50 )->where( 'quantity' , '<' , 1 )->update( [ 'imported' => 0 ] );
		 foreach( $this->getCategories() as $category ) {
			for( $i = 1 ; $i < 6 ; $i++ ) {
			   if( $items = $this->getXpathProducts( $category[ 'url' ] . '/' . $i , $this->ch ) ) {
				  foreach( $items as $item ) {
					 print_r( '.' );
					 try {
						$model   = '10-' . explode( '/' , $item->childNodes[ 1 ]->getAttribute( 'href' ) )[ 1 ];
						$product = Product::where( 'model' , $model )->first();
						if( $product ) {
						   if( $product->quantity > 50 || $product->quantity < 1 ) {
							  $updateProduct[] = $product->id;
							  $edited++;
						   }
						} else {
						   $insertProduct[] = $this->getProductDataToInsert( $item , $category[ 'category_id' ] );
						   $added++;
						}

					 } catch( \Exception $e ) {
						$error++;
						Log::channel( 'log' )->info( $e->getMessage() );
					 }
				  }
			   }
			}
		 }
		 $log = 'Frissítve : ' . $edited . '| Hozzáadva : ' . $added . ' | Error : ' . $error;
		 //echo $log;
		 Log::channel( 'log' )->info( $log );
		 if( File::exists( public_path( $this->cookieFile ) ) ) {
			File::delete( public_path( $this->cookieFile ) );
		 }
		 Product::whereIn( 'id' , $updateProduct )->update( [
																'imported'   => 1 ,
																'quantity'   => 100 ,
																'updated_at' => date( 'Y-m-d H:i:s' ) ,
															] );
		 foreach( $insertProduct as $product ) {
			try {
			   Product::insert( $product );
			} catch( \Exception $exception ) {
			   Log::channel( 'log' )->info( ' Insert - ' . $exception->getMessage() );
			}
		 }
		 Product::where( 'model' , 'like' , '%10-%' )->where( 'imported' , 0 )->update( [
																							'quantity'     => 0 ,
																							'out_of_stock' => 'Jelenleg nem elérhető'
																						] );

	  }

	  private function getProductDataToInsert( $item , $category_id = 0 )
	  {
		 $random    = Str::random( 4 );
		 $aruId     = explode( '/' , $item->childNodes[ 1 ]->getAttribute( 'href' ) )[ 1 ];
		 $model     = '10-' . $aruId;
		 $nev       = $item->childNodes[ 4 ]->nodeValue;
		 $leiras    = $this->getXpathProductDescription( $item->childNodes[ 1 ]->getAttribute( 'href' ) );
		 $seoUrl    = Str::slug( $nev , '-' ) . '-' . ( $aruId ? $aruId : $random );
		 $imageName = Str::slug( $nev , '_' ) . '_' . ( $aruId ? $aruId : $random );
		 $imageUrl  = 'kepek/' . $imageName . '.jpg';
		 //Kép
		 $file = public_path( $imageUrl );
		 if( !\Illuminate\Support\Facades\File::exists( $file ) ) {
			try {
			   Image::make( 'https://example.com/photos/' . $aruId . '.jpg' )->save( $file );
			} catch( \Exception $exception ) {
			   $log = $exception->getMessage();
			   Log::channel( 'error' )->info( 'Képletöltés hiba - ' . $log );
			}

		 }
		 $product = [
			 'name'              => $item->childNodes[ 4 ]->nodeValue ,
			 'short_name'        => '' ,
			 'model'             => $model ,
			 'sku'               => $aruId ,
			 'shop_id'           => 2 ,
			 'aruId'             => '' ,
			 'tag'               => '' ,
			 'meta_title'        => $nev ,
			 'meta_keyword'      => $nev ,
			 'meta_description'  => $nev ,
			 'description'       => $leiras ? $leiras[ 0 ]->nodeValue : '' ,
			 'short_description' => '' ,
			 'out_of_stock'      => 'Jelenleg nem elérhető' ,
			 'beszer_ar'         => 0 ,
			 'sajat_ar'          => 0 ,
			 'price'             => 0 ,
			 'special'           => null ,
			 'image'             => $imageUrl ,
			 'manufacturer_id'   => '' ,
			 'weight'            => '' ,
			 'complete'          => 0 ,
			 'in_shop'           => 1 ,
			 'imported'          => 1 ,
			 'viewed'            => 0 ,
			 'seo_url'           => $seoUrl ,
			 'quantity'          => 100 ,
			 'category_id'       => $category_id ,
			 'parent_product_id' => null ,
			 'kiveve'            => 0 ,
			 'status'            => 1 ,
			 'created_at'        => date( 'Y-m-d H:i:s' ) ,
			 'updated_at'        => '' ,
			 'vat_number'        => '' ,
		 ];
		 return $product;
	  }

	  private function getProductDataToUpdate( $item , $product )
	  {

		 return [
			 'imported'   => 1 ,
			 'quantity'   => 100 ,
			 'updated_at' => date( 'Y-m-d H:i:s' ) ,
		 ];
	  }

	  private function generateProductXML( $productsData = [] )
	  {
		 $dom               = new DOMDocument();
		 $dom->encoding     = 'utf-8';
		 $dom->xmlVersion   = '1.0';
		 $dom->formatOutput = true;
		 // File
		 $xml_file_name = $this->products_xml_file;
		 $root = $dom->createElement( 'Products' );
		 foreach( $productsData as $product ) {
			$product_node = $dom->createElement( 'product' );
			$attr = new \DOMAttr( 'ID' , $product[ 'product_id' ] );
			$product_node->setAttributeNode( $attr );
			// Child
			$child_node_name = $dom->createElement( 'name' , htmlspecialchars( $product[ 'name' ] ) );
			$product_node->appendChild( $child_node_name );
			$child_node_description = $dom->createElement( 'description' , htmlspecialchars( $product[ 'description' ] ) );
			$product_node->appendChild( $child_node_description );
			$child_node_imgUrl = $dom->createElement( 'imgUrl' , $product[ 'imgUrl' ] );
			$product_node->appendChild( $child_node_imgUrl );
			$child_node_category_id = $dom->createElement( 'category' , $product[ 'category_id' ] );
			$product_node->appendChild( $child_node_category_id );
			$child_node_category_name = $dom->createElement( 'category_name' , $product[ 'category_name' ] );
			$product_node->appendChild( $child_node_category_name );
			if( isset( $product_node ) ) {
			   $root->appendChild( $product_node );
			}
		 }
		 if( isset( $product_node ) ) {
			$dom->appendChild( $root );
			$dom->save( $xml_file_name );
		 } else {
			echo 'Hiba';
		 }
	  }

	  private function generateProductPriceXML( $productsData = [] )
	  {
		 $dom               = new DOMDocument();
		 $dom->encoding     = 'utf-8';
		 $dom->xmlVersion   = '1.0';
		 $dom->formatOutput = true;
		 // File
		 $xml_file_name = $this->products_price_xml_file;
		 $root = $dom->createElement( 'Products' );
		 foreach( $productsData as $product ) {
			$product_node = $dom->createElement( 'product' );
			$attr = new \DOMAttr( 'ID' , $product[ 'product_id' ] );
			$product_node->setAttributeNode( $attr );
			// Child
			$child_node_price = $dom->createElement( 'ArBrutto' , $product[ 'price' ] );
			$product_node->appendChild( $child_node_price );
			if( isset( $product_node ) ) {
			   $root->appendChild( $product_node );
			}
		 }
		 if( isset( $product_node ) ) {
			$dom->appendChild( $root );
			$dom->save( $xml_file_name );
		 } else {
			echo 'Hiba';
		 }
	  }

	  private function getXpathProducts( $url , $ch )
	  {
		 $xpathQuery = $this->productsXPATH;
		 curl_setopt( $ch , CURLOPT_URL , $url );
		 curl_setopt( $ch , CURLOPT_RETURNTRANSFER , true );
		 curl_setopt( $ch , CURLOPT_FOLLOWLOCATION , false );
		 if( $this->login ) {
			curl_setopt( $ch , CURLOPT_COOKIEJAR , $this->cookieFile );
		 }
		 curl_setopt( $ch , CURLOPT_SSL_VERIFYHOST , false );
		 curl_setopt( $ch , CURLOPT_SSL_VERIFYPEER , false );
		 $res = curl_exec( $ch );
		 curl_close( $ch );
		 $dom = new DomDocument();
		 @$dom->loadHTML( $res );
		 $xpath = new DOMXPath( $dom );
		 if( $xpath->query( $xpathQuery )->length == 0 ) {
			return false;
		 }
		 return $xpath->query( $xpathQuery );
	  }

	  private function getXpathProductDescription( $url )
	  {
		 $xpathQuery = $this->descriptXPATH;
		 $res        = Http::get( $this->resourceUrl . $url )->body();
		 $dom = new DomDocument();
		 @$dom->loadHTML( $res );
		 $xpath = new DOMXPath( $dom );
		 if( $xpath->query( $xpathQuery )->length == 0 ) {
			return false;
		 }
		 return $xpath->query( $xpathQuery );
	  }

	  private function getCategories()
	  {
	  	// Természetesen ebben eredetileg több van
			return [[
					'url'           => 'https://example.com' ,
					'category_id'   => 29 , // Saját kategória id-ja
					'category_name' => ''
				]];
	  }

	  public function getResult( $url , $xpathQuery , $ch )
	  {
		 curl_setopt( $ch , CURLOPT_URL , $url );
		 curl_setopt( $ch , CURLOPT_RETURNTRANSFER , true );
		 curl_setopt( $ch , CURLOPT_FOLLOWLOCATION , false );
		 curl_setopt( $ch , CURLOPT_COOKIEJAR , 'cookie_jar.txt' );
		 curl_setopt( $ch , CURLOPT_SSL_VERIFYHOST , false );
		 curl_setopt( $ch , CURLOPT_SSL_VERIFYPEER , false );
		 $res = curl_exec( $ch );
		 curl_close( $ch );
		 $dom = new DomDocument();
		 @$dom->loadHTML( $res );
		 $xpath = new DOMXPath( $dom );
		 if( $xpath->query( $xpathQuery )->length == 0 ) {
			return false;
		 }
		 return $xpath->query( $xpathQuery );
	  }

	  public function login()
	  {
		 $postValues = [
			 'username' => $this->username ,
			 'pass'     => $this->password ,
		 ];
		 $curl = curl_init();
		 curl_setopt( $curl , CURLOPT_URL , $this->loginUrl );
		 curl_setopt( $curl , CURLOPT_POST , true );
		 curl_setopt( $curl , CURLOPT_POSTFIELDS , http_build_query( $postValues ) );
		 curl_setopt( $curl , CURLOPT_SSL_VERIFYHOST , false );
		 curl_setopt( $curl , CURLOPT_SSL_VERIFYPEER , false );
		 curl_setopt( $curl , CURLOPT_COOKIEJAR , $this->cookieFile );
		 curl_setopt( $curl , CURLOPT_RETURNTRANSFER , true );
		 curl_setopt( $curl , CURLOPT_REFERER , $this->resourceUrl );
		 curl_setopt( $curl , CURLOPT_FOLLOWLOCATION , false );
		 curl_exec( $curl );
		 if( curl_errno( $curl ) ) {
			throw new Exception( curl_error( $curl ) );
		 }
		 return $curl;
	  }

	  private function logout() {
		 $postValues = [
			 'username' => $this->username ,
			 'pass'     => $this->password ,
		 ];
		 $curl = curl_init();
		 curl_setopt( $curl , CURLOPT_URL , $this->logoutUrl );
		 curl_setopt( $curl , CURLOPT_POST , true );
		 curl_setopt( $curl , CURLOPT_POSTFIELDS , http_build_query( $postValues ) );
		 curl_setopt( $curl , CURLOPT_SSL_VERIFYHOST , false );
		 curl_setopt( $curl , CURLOPT_SSL_VERIFYPEER , false );
		 curl_setopt( $curl , CURLOPT_COOKIEJAR , $this->cookieFile );
		 curl_setopt( $curl , CURLOPT_RETURNTRANSFER , true );
		 curl_setopt( $curl , CURLOPT_REFERER , $this->resourceUrl );
		 curl_setopt( $curl , CURLOPT_FOLLOWLOCATION , false );
		 curl_exec( $curl );
	  }

   }