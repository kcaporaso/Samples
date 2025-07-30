<?php
/**
 *  1. Write a function that will take a string of at least 5 characters and reverse it. (For example: "I am here, looking at youâ" :: "uoy ta gnikool ,ereh ma I")
 *
 */
echo reverseString("goforit");

function reverseString($in) {

   if (strlen($in) > 5) {
      return strrev($in) . '<br/>';
   } else {
      return "string to short<br/>";
   }
}

/* 2. sort array of numbers ascending */
$arr = array(4, 10, 8, 34, 35, 12, 1, 9, 8, 14, 28);
sort($arr, SORT_NUMERIC);
echo 'Ascending:<br/>';
print_r($arr);

/* 3. sort array of numbers descending no dup values */
$arr = array_unique($arr); // throw out the dups
rsort($arr, SORT_NUMERIC);
echo '<br/>Descending:<br/>';
print_r($arr);

/*
 * 4. You have a web service that you consume; it returns an array of data. The array contains 5 nodes of data in each element. 
 * Write a function that would be able to consume the web service and display each data set to the view.
 *
 */
$ws_arr = array( "element1" => 
                     array("node1"=>"node 1 data", "node2"=>"node 2 data", "node3"=>"node 3 data", "node4"=>"node 4 data","node5"=>"node 5 data"),
                 "element2"=>
                     array("node1"=>"node 1 data", "node2"=>"node 2 data", "node3"=>"node 3 data", "node4"=>"node 4 data","node5"=>"node 5 data"),
          );

echo '<br/><br/>Consume and Display Web Service Results:<br/>';
ws_consumer_display($ws_arr);
function ws_consumer_display($ws_arr) {

   foreach ($ws_arr as $key => $value) {
     echo $key . "<br/>";
     foreach ($value as $k => $v) {
        echo $k . " = " . $v . "<br/>";
     }
     echo "<br/>";
   }
   // debug var_dump($ws_arr);
}


/**
 * 5. Write out three different ways to print whole numbers divisible by 2, starting with 1 and going thru 20.
 */
// Use modulus
echo '<br/>Method 1:<br/>';
for ($i=1; $i<=20; $i++) {
   /* method 1 */
   if (($i % 2) == 0) {
      echo  $i . '<br/>';
   }

}

echo '<br/>Method 2:<br/>';
// Use a test for a deciaml, means we didn't divide evenly by 2.
for ($i=1; $i<=20; $i++) {
   /* method 2 */
   $result = ($i / 2); 
   $result = strval($result);
   if (!strpos($result, '.')) {
      echo $i . '<br/>';
   }
}

echo '<br/>Method 3:<br/>';
// Check the last digit for 0,2,4,8
$test_array = array(0,2,4,8);
for ($i=1; $i<=20; $i++) {
   /* method 3 */
   $i = strval($i);  // make a string
   $v = $i[strlen($i)-1]; 
   if (in_array($v, $test_array)) {
      echo $i . '<br/>';
   }

}



/**
 * 6. Write a class definition to connect to a database using the username/password of user1/pass1; 
 *    include the CRUD functions in this class.
 *    Going to use pdo calls directly for my example.
 **/

class Database
{
   const USERNAME = 'user1';
   const PASSWORD = 'pass1';
   const SERVER = 'localhost';
   const DBNAME = 'test';
   const DRIVER = 'mysql';
   
   private $pdo;
   private $pdo_dsn;

   function __construct() {
      $this->pdo_dsn = self::DRIVER.":host=".self::SERVER.";dbname=".self::DBNAME;
   }

   public function db_connect() {

      try {

         $this->pdo = new PDO($this->pdo_dsn, self::USERNAME, self::PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
         $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

      }  catch (PDOException $e) {
         die(print_r($e->getMessage()));
      }
   }

   public function db_create($query, $params) { 

      try {

         $stmt = $this->pdo->prepare($query);
         $stmt->execute($params);

         $id = $this->pdo->lastInsertId();

         return $id;

      }  catch (PDOException $e) {
         die(print_r($e->getMessage()));
      }
   }

   public function db_retrieve($query, $params, $fetch_method = 'object') {
  
      try {
         $stmt = $this->pdo->prepare($query);
         $stmt->execute($params);
       
         if ($fetch_method == 'object') {
            $data = $stmt->fetchAll(PDO::FETCH_OBJ);
         } elseif ($fetch_method == 'array') {
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
         } else {
            $data = $stmt->fetchAll(PDO::FETCH_BOTH);
         }
     
         return $data;

      } catch (PDOException $e) {
         var_dump($stmt->queryString);
         die(print_r($e->getMessage()));
      }
   }

   public function db_update($query, $params) {

      try {
  
         $stmt = $this->pdo->prepare($query);
         $stmt->execute($params);
  
         $affected_rows = $stmt->rowCount();
      
         return $affected_rows;
  
      } catch (PDOException $e) {
         die(print_r($e->getMessage()));
      }
   }

   function db_delete($query, $params) {

      try {
  
         $stmt = $this->pdo->prepare($query);
         $stmt->execute($params);
  
         $affected_rows = $stmt->rowCount();
      
         return $affected_rows;
  
      } catch (PDOException $e) {
         die(print_r($e->getMessage()));
      }
   }

   /*
    * 7. Using the class created in question 6, create a method to read data from one table, serialize it, and post to "table2". 
    *    Make sure to check for errors and null values.
    */
   function db_remote_copy($from_table, $to_table) {

      $from_query = "SELECT * FROM $from_table";
      $from_params = array();
      $from_data = $this->db_retrieve($from_query, $from_params, 'array');
      $sql = '';
      
      if (!empty($from_data)) {
         // serialize: 
         $from_data_str = serialize($from_data);
         //$to_data = unserialize($from_data_str);

         // now post to table2. 
         //var_dump($from_data);
         $to_params = array();
         foreach ($from_data as $key => $value) {
            echo $key . '=' . $value . '<br/>';
            $sql = "INSERT INTO $to_table ";
            $col = '';
            $val = '';
            foreach ($value as $column => $v) {
               if (!empty($column) && !(empty($v))) {
                  if (!empty($col)) { 
                    $col .= ', ' . $column; 
                  } else {
                    $col .= $column;
                  }  
                  
                  if (!empty($val)) {
                    $val .= ', ' . $v;
                  } else {
                    $val .= $v; 
                  }
               }
            }
            $sql .= '(' . $col . ') VALUES (' . $val . ')';
            $col = '';
            $val = '';
            $this->db_create($sql, $to_params); //START HERE.
            //echo $sql;
         }
         
      }
   }

} // end Database class
$db = new Database();
$db->db_connect();
$db->db_remote_copy('table1', 'table2');

/**
 * 10.
 * An array has 99 elements, containing unique integers in the range 
 * of 1 through 100. Write a function to determine which integer is not 
 * included in the array.
 */
// I'd likely have this as a static array in some class so it's only
// create once. build the 'control' integer array:
$int_arr = array();
for ($i=0; $i<=99; $i++) {
   $int_arr[] = $i;
}
// Given array of ints missing some values.
$mis_arr = array();
for ($i=0; $i<=99; $i++) {
   if ($i % 2) {
      $mis_arr[] = $i;
   }
}
// function to give you what's missing from our control array.
function find_missing_int($int_arr, $mis_arr) {
   return array_diff($int_arr, $mis_arr);
}
var_dump(find_missing_int($int_arr, $mis_arr));

/**
 * 11.  BONUS Questions: Reverse a linked list, use recursive.
 *
 * In the interest of time I better skip this one for now - 
 * it's fairly complex.
 */

/**
 * 12. BONUS Questions: Reverse a linked list, use non-recursive.
 * Again in the interest of time I haven't had a chance to validate
 * completely.
 */
// visual aid
// list [nd | nn]->[nd | nn]->[nd | nn]
function reverse_linked_list_iterative($list) {

   if (empty($list)) { return; } // list was empty.

   if ($list->nd != NULL) {
      if ($list->nd->nn != NULL) {
         $cur_nd = $list->nd;  // store first node.
         $new_nd = NULL;
          
         while ($cur_nd != NULL) {
            $tmp_nd = $cur_nd->nn;
            $cur_nd->nn = $new_nd;
            $new_nd = $cur_nd; 
            $cur_nd = $tmp_nd;
         }
         $list->nd = $new_nd;
      }
   } 
}

?>
