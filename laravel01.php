<?php

   /**
	* Ez egy terméknek az árait mutatja 1 hónapra, vagy 1 évre visszamenőleg
	* A view-ban json formátumban dolgozza fel a lekért adatokat és jeleníti meg egy diagramon
	* Framework - Laravel
	*
	*/

   class PriceChart{
	  public function index()
	  {

		 $year_chart = true;
		 $month_chart = false;

		 $product_id = 7047;

		 $product = \App\Models\Product\Product::find($product_id);


		 $month = 5;
		 $year = 2022;

		 if($year_chart){

			$days = 30;
			$from = date('Y-m-d', strtotime("-{$days} day", strtotime(date('Y-m-d H:i:s'))));
			$to = date('Y-m-d H:i:s');

			$prices = Prices::whereBetween('price_date',[$from, $to])->where('product_id',$product_id)->get();
		 }

		 if($month_chart){
			$prices = Prices::whereMonth('price_date',$month)->where('product_id',$product_id)->get();
		 }

		 $labels = [];
		 $pr_prices = [];

		 foreach($prices as $price){
			$pr_prices[] = $price->price;
			$labels[] = date('d',strtotime($price->price_date)) . '.';
		 }

		 $data['labels'] = $labels;
		 $data['price_data'] =  $pr_prices;

		 return view('Product.price_chart',$data,['product'=>$product]);

	  }
   }

