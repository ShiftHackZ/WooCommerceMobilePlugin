<?php
/**
 * @package WooApp
 * @version 1.0.0
 */
/*
Plugin Name: WooApp
Plugin URI: http://moroz.cc
Description: WooApp
Author: Dmitriy Moroz
Version: 1.0.0
Author URI: https://moroz.cc
*/

class WooShop {
	public $id;
	public $lat;
	public $lng;
	public $name;
}

class WooAttr {
	public $slug;
	public $name;
	public $terms;
}

class WooTerm {
	public $slug;
	public $name;
}

add_action( 'rest_api_init', 'wp_rest_filterproducts_endpoints');


function wp_rest_filterproducts_endpoints($request) {
    register_rest_route('wp/v3', 'filter/products', array(
        'methods' => 'GET',
        'callback' => 'wp_rest_filterproducts_endpoint_handler',
    ));
    register_rest_route('wp/v3', 'shopmap', array(
        'methods' => 'GET',
        'callback' => 'wp_rest_shop_map',
    ));
    register_rest_route('wp/v3', 'variations', array(
        'methods' => 'GET',
        'callback' => 'wp_rest_product_variations',
    ));
}

function wp_rest_product_variations($request = null) {
  $params = $request->get_params();

  $id = $params['id'];
            
  $output = GetProductAllParamsById( $id );
  
  return new WP_REST_Response($output, 123);
}

function wp_rest_shop_map() {
	$output = array();
	
	$output[0] = new WooShop();
	$output[0]->id = 0;
	$output[0]->lat = 48.41471900878479;
	$output[0]->lng = 35.080945944642366;
	$output[0]->name = 'Tochka vidachi';
	
	return new WP_REST_Response($output, 123);
}

function wp_rest_filterproducts_endpoint_handler($request = null) {
    $output = array();
    
    $params = $request->get_params();

    $category = $params['category'];
            
    $filters  = $params['filter'];
    $per_page = $params['per_page'];
    $offset   = $params['offset'];
    $order    = $params['order'];
    $orderby  = $params['orderby'];
    
    // Use default arguments.
    $args = [
      'post_type'      => 'product',
      'posts_per_page' => 10,
      //'post_status'    => 'publish',
      'paged'          => 1,
    ];
    
    // Posts per page.
    if ( ! empty( $per_page ) ) {
      $args['posts_per_page'] = $per_page;
    }

    // Pagination, starts from 1.
    if ( ! empty( $offset ) ) {
      $args['paged'] = $offset;
    }

    // Order condition. ASC/DESC.
    if ( ! empty( $order ) ) {
      $args['order'] = $order;
    }

    // Orderby condition. Name/Price.
    if ( ! empty( $orderby ) ) {
      if ( $orderby === 'price' ) {
        $args['orderby'] = 'meta_value_num';
      } else {
        $args['orderby'] = $orderby;
      }
    }
    
        // If filter buy category or attributes.
    if ( ! empty( $category ) || ! empty( $filters ) ) {
      $args['tax_query']['relation'] = 'AND';

      // Category filter.
      if ( ! empty( $category ) ) {
        $args['tax_query'][] = [
          'taxonomy' => 'product_cat',
          'field'    => 'slug',
          'terms'    => [ $category ],
        ];
      }

      // Attributes filter.
      if ( ! empty( $filters ) ) {
        foreach ( $filters as $filter_key => $filter_value ) {
          if ( $filter_key === 'min_price' || $filter_key === 'max_price' ) {
            continue;
          }

          $args['tax_query'][] = [
            'taxonomy' => $filter_key,
            'field'    => 'term_id',
            'terms'    => \explode( ',', $filter_value ),
          ];
        }
      }

      // Min / Max price filter.
      if ( isset( $filters['min_price'] ) || isset( $filters['max_price'] ) ) {
        $price_request = [];

        if ( isset( $filters['min_price'] ) ) {
          $price_request['min_price'] = $filters['min_price'];
        }

        if ( isset( $filters['max_price'] ) ) {
          $price_request['max_price'] = $filters['max_price'];
        }

        $args['meta_query'][] = \wc_get_min_max_price_meta_query( $price_request );
        }
    }
    
    $the_query = new \WP_Query( $args );

    if ( ! $the_query->have_posts() ) {
      return $output;
    }
                        
    while ( $the_query->have_posts() ) {
        $the_query->the_post();
        $product = wc_get_product( get_the_ID() );  
		$image_id  = $product->get_image_id();

        // Product Properties
        $wcproduct['id'] = $product->get_id();
        $wcproduct['name'] = $product->get_name();
		$wcproduct['slug'] = $product->get_slug();
		$wcproduct['permalink'] = $product->get_permalink();
		$wcproduct['price'] = $product->get_price();
		$wcproduct['regular_price'] = $product->get_regular_price();
		$wcproduct['sale_price'] = $product->get_sale_price();
		$wcproduct['image'] = wp_get_attachment_image_url( $image_id, 'full' );
                    
        $output[] = $wcproduct;
    }
    wp_reset_postdata();

    return new WP_REST_Response($output, 123);
}

function GetProductAllParamsById($_idProduct = null) {

  if ( func_num_args() > 0 ) {

       $result = Array();
       $productAllAttr = get_post_meta( $_idProduct, '_product_attributes' );
       foreach ($productAllAttr as $value) {

           while (count($value) > 0) {
               $wooattr = new WooAttr();
               //$wooterms = Array();
               $_instValue = array_pop($value);
               $_slugParam = $_instValue['name'];
               $_nameParam = wc_attribute_label( $_slugParam );
               //$_termsSlugs = wc_get_product_terms( $_idProduct, $_slugParam, array( 'fields' => 'slugs', 'fields' => 'names' ) ); 
               $_terms = wc_get_product_terms( $_idProduct, $_slugParam ); 


               /*foreach ($_terms as $term) { 
                 $wooterm = new WooTerm();
                 vardump($term);
                 $wooterm->name = array_pop($term)['name'];
                 $wooterm->slug = array_pop($term)['slug'];
                 array_push($wooterms, $wooterm);
               }*/

               $wooattr->name = $_nameParam;
               $wooattr->slug = $_slugParam;
               $wooattr->terms = $_terms;

               //array_push($result, [$_nameParam => $_nameProductAttr]);
               array_push($result, $wooattr);
           }

       }  

   }

   return isset($result) ? $result : null;
}
