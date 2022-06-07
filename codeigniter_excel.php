<?php
   defined('BASEPATH') OR exit('No direct script access allowed');

   /**
	* Ez egy excel file-lal dolgozik. Egy régebbi úgyszint termékekhez kapcsolódó project
	*/
   class Import_model extends CI_Model
   {
	  private $objPHPExcel = '';

	  public function __construct()
	  {
		 $this->load->library('excel');
	  }

	  public function loadFile()
	  {
		 $config['upload_path']          = '../excel/';
		 $config['allowed_types']        = 'xls';
		 $config['max_size']             = 10000;

		 $this->load->library('upload', $config);
		 if ( ! $this->upload->do_upload('file'))
		 {
			$error = array('error' => $this->upload->display_errors());
			$this->session->set_flashdata('msg','File feltöltése sikertelen');
		 }
		 else
		 {
			$data = array('upload_data' => $this->upload->data());
			//read file from path
			$file = '../excel/'.$_FILES['file']['name'];
			$this->objPHPExcel = PHPExcel_IOFactory::load($file);

			$msg  = "<strong>File feltöltve:</strong> ".$_FILES['file']['name']."<br>";
			$msg .= "Import: ".$this->objPHPExcel->getActiveSheet()->getTitle();

			$this->session->set_flashdata('msg',$msg);
			$this->session->set_flashdata('file',$_FILES['file']['name']);
			$this->session->set_flashdata('has_file',1);
		 }
	  }

	  public function GetExcelFile()
	  {
		 $file = '../excel/'.$this->input->post('excel_file');
		 $this->objPHPExcel = PHPExcel_IOFactory::load($file);
		 //get only the Cell Collection
		 $cell_collection = $this->objPHPExcel->getActiveSheet()->getCellCollection();

		 switch ($this->objPHPExcel->getActiveSheet()->getTitle())
		 {
			case 'categories':
			   $this->UpdateCategories($cell_collection);
			   unlink($file);
			   $this->session->set_flashdata('info','Sikeres kategória importálás!');
			   redirect('import');
			   break;
			case 'products':
			   $this->UpdateProducts($cell_collection);
			   unlink($file);
			   $this->session->set_flashdata('info','Sikeres termék importálás!');
			   redirect('import');
			   break;

			default:
			   # code...
			   break;
		 }
	  }

	  public function UpdateCategories($cell_collection)
	  {
		 //extract to a PHP readable array format
		 foreach ($cell_collection as $cell) {
			$row = $this->objPHPExcel->getActiveSheet()->getCell($cell)->getRow();

			if ($row !== 1)
			{
			   $data[$row] = array(
				   'id'           => (int)$this->objPHPExcel->getActiveSheet()->getCell('A'.$row)->getValue(),
				   'name'         => (string)$this->objPHPExcel->getActiveSheet()->getCell('B'.$row)->getValue(),
				   'parent_id'    => (int)$this->objPHPExcel->getActiveSheet()->getCell('C'.$row)->getValue(),
				   'seo_url'      => $this->generateSeoURL((string)$this->objPHPExcel->getActiveSheet()->getCell('B'.$row)->getValue())
			   );
			}
		 }


		 // Kiüríti a táblát
		 //$this->db->query('TRUNCATE TABLE  categories;');

		 //Hozzáadja a táblához
		 foreach ($data as $category)
		 {

			$row = $this->db->get_where('categories',array('id'=>$category['id']))->num_rows();
			if ($row == 0)
			{
			   $this->db->insert('categories',array('category_name'=>$category['name'],'parent_id'=>(int)$category['parent_id'],'seo_url'=>$category['seo_url']));
			}
			else
			{
			   $this->db->update('categories',array('category_name'=>$category['name'],
													'parent_id'=>$category['parent_id'],'seo_url'=>$category['seo_url']),"id='".(int)$category['id']."'");
			}
		 }
	  }

	  /*
	   * Termékek feltöltése excel file-ból
	   *
	   * @param $cell_collection
	   *
	   */
	  public function UpdateProducts($cell_collection)
	  {
		 //extract to a PHP readable array format
		 foreach ($cell_collection as $cell) {
			$row = $this->objPHPExcel->getActiveSheet()->getCell($cell)->getRow();

			if ($row !== 1)
			{
			   if (is_file($this->objPHPExcel->getActiveSheet()->getCell('I'.$row)->getValue()))
			   {
				  $image = $this->objPHPExcel->getActiveSheet()->getCell('I'.$row)->getValue();
			   }
			   else
			   {
				  $image = 'img/no_image.png';
			   }
			   $image = $this->objPHPExcel->getActiveSheet()->getCell('I'.$row)->getValue();

			   $product_id     = $this->objPHPExcel->getActiveSheet()->getCell('A'.$row)->getValue();
			   $cikkszam       = (string)$this->objPHPExcel->getActiveSheet()->getCell('B'.$row)->getValue();
			   $name           = (string)$this->objPHPExcel->getActiveSheet()->getCell('C'.$row)->getValue();
			   $price          = (empty($this->objPHPExcel->getActiveSheet()->getCell('D'.$row)->getValue())?'':$this->objPHPExcel->getActiveSheet()->getCell('D'.$row)->getValue());
			   $discount       = (string)$this->objPHPExcel->getActiveSheet()->getCell('E'.$row)->getValue();
			   $description    = (empty($this->objPHPExcel->getActiveSheet()->getCell('F'.$row)->getValue())?'':$this->objPHPExcel->getActiveSheet()->getCell('E'.$row)->getValue());
			   $tipus          = (empty($this->objPHPExcel->getActiveSheet()->getCell('G'.$row)->getValue())?'':$this->objPHPExcel->getActiveSheet()->getCell('F'.$row)->getValue());
			   $category_id    = $this->objPHPExcel->getActiveSheet()->getCell('H'.$row)->getValue();
			   $status         = $this->objPHPExcel->getActiveSheet()->getCell('J'.$row)->getValue();
			   $seo_url        = $this->generateSeoURL($this->objPHPExcel->getActiveSheet()->getCell('C'.$row)->getValue());


			   $data[$row] = array(
				   'product_id'       => $product_id,
				   'cikkszam'         => $cikkszam,
				   'name'             => $name,
				   'price'            => $price,
				   'discount'         => $discount,
				   'description'      => $description,
				   'tipus'            => $tipus,
				   'category_id'      => $category_id,
				   'product_image'    => $image,
				   'status'           => $status,
				   'seo_url'          => $seo_url
			   );
			}
		 }


		 // Kiüríti a táblát
		 //$this->db->query('TRUNCATE TABLE  products;');

		 //Hozzáadja a táblához
		 foreach ($data as $item)
		 {

			$product = array(
				'product_id'       => $item['product_id'],
				'cikkszam'         => $item['cikkszam'],
				'name'             => $item['name'],
				'price'            => $item['price'],
				'discount'         => $item['discount'],
				'description'      => $item['description'],
				'tipus'            => $item['tipus'],
				'category_id'      => $item['category_id'],
				'product_image'    => $item['product_image'],
				'status'           => $item['status'],
				'seo_url'          => $item['seo_url']

			);

			$row = $this->db->get_where('products',array('product_id'=>$product['product_id']))->num_rows();
			if ($row == 0)
			{
			   $this->db->insert('products',$product);
			}
			else
			{
			   $this->db->update('products',$product,"product_id='".(int)$product['product_id']."'");
			}



		 }
	  }

	  function SaveDataToXls($table_name)
	  {
		 $query = $this->db->get($table_name);

		 if($query)
		 {

			// Starting the PHPExcel library
			$this->load->library('excel');
			$this->load->library('iofactory');

			$objPHPExcel = new PHPExcel();

			$objPHPExcel->setActiveSheetIndex(0);

			// Field names in the first row
			$fields = $query->list_fields();
			$col = 0;
			foreach ($fields as $field)
			{
			   $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col, 1, $field);
			   $col++;
			}

			// Fetching the table data
			$row = 2;
			foreach($query->result() as $data)
			{
			   $col = 0;
			   foreach ($fields as $field)
			   {
				  $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col, $row, $data->$field);
				  $col++;
			   }

			   $row++;
			}

			$objPHPExcel->setActiveSheetIndex(0);
			$objPHPExcel->getActiveSheet()->setTitle($table_name);

			$objWriter = IOFactory::createWriter($objPHPExcel, 'Excel5');
			$filename = $table_name.'_products.xls';

			// Sending headers to force the user to download the file
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="'.$filename.'"');
			header('Cache-Control: max-age=0');

			$objWriter->save('php://output');
		 }
	  }

	  function generateSeoURL($string, $wordLimit = 150)
	  {
		 $separator = '-';
		 $string = strtolower($string);

		 if($wordLimit != 0){
			$wordArr = explode(' ', $string);
			$string = implode(' ', array_slice($wordArr, 0, $wordLimit));
		 }

		 $quoteSeparator = preg_quote($separator, '#');

		 $trans = array(
			 'ö' => 'o',
			 'ü' => 'u',
			 'ó' => 'o',
			 'ő' => 'o',
			 'ú' => 'u',
			 'é' => 'e',
			 'á' => 'a',
			 'ű' => 'u',
			 'í' => 'i',
			 '&.+?;'                    => '',
			 '[^\w\d _-]'            => '',
			 '\s+'                    => $separator,
			 '('.$quoteSeparator.')+'=> $separator
		 );

		 $string = strip_tags($string);
		 foreach ($trans as $key => $val){
			$string = preg_replace('#'.$key.'#i'.(UTF8_ENABLED ? 'u' : ''), $val, $string);
		 }



		 return trim(trim($string, $separator));
	  }





   }