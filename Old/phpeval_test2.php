PHP/MySQL Evaluation Test

MYSQL: 
======= 

1- Explain the differences between MyISAM and InnoDB engines. Why would you choose one or the other? 

MyISAM used to be the default mysql db engine, but lacks things that the InnoDB can do. InnoDB has great performance for dealing with high-volume, it supports transactions and row-level locking.  Anything that's going to get used heavily should stay at InnoDB.  Looks like you need MyISAM for MySQL replication.


2- What is the best way to speed up a query? 

Cache the query results :)
That's a fairly loaded question there are a variety of ways, but it starts with pure analysis of the table structure and how tables are xref'ed and are we using proper indexing, avoid full-table scans, etc.



3- How do you debug a slow query? 
Start by turning on the slow query log setting and then I approach it by breaking down the query into smaller parts, divide and conquer approach.
There are some mysql profilers out there as well that could assist.  You could also ask another developer to have a look to see if they can see a better way to author the query.


4- What is wrong with the following SQL: 

select name, created_date from user group by gender; 
Seems you didn't select 'gender' out of 'user'


5- How do I return results of a join including all the data of the first table regardless of whether the second table has matching records? 
You'd use a Left Join to accomplish this.


6- In a join again, how do I return all the records of the TWO tables regardless of match WHILE still showing a match if there is one? 
Getting into a full join (cross join) here.
select t1.*, t2.* from t1, t2 where t1.first_name = t2.first_name; 


ex joining two tables on first name: 

t1                        t2 
John    male       null    null 
Marc   male       Marc  32 
null     null          Jake    40 

7- Imagine a fundraiser (event_id) and a participant (event_id, amount) tables. How do I return a list of events that have more than 3 participants who have given more than $100? 

Given this table structure:
fund               participant
---------------|---------------------------
event_id    | event_id | amount
This will do it, making use of sub queries.

select tot.* from 
	(select count(*) as total, sum(p.amount) as amt from participant p group by p.event_id) as tot 
	where tot.total >= 3 and tot.amt > 100


8- When to choose a datetime vs date vs timestamp type for a date/time column? 

1. depends on the level of granularity of time you want to keep about the entity.  datetime has the date and time elements, where date only has the date element stored.  timestamp gives you a point in time and will handle timezone conversions to UTC and back, whereas the others are not converted, they're time is stored at the GLOBAL.time_zone setting, and if that's set to SYSTEM then it picks it up off your OS; so it can be more of a challenge to deal with timezone displaying properly.


PHP: 
==== 

1- given the following table: 

product: 
======= 

id 
name 
added 

use PDO to write a query to search products by name. The search keyword is passed by url using variable 'q' 


//assuming $this->pdo is a valid PDO object with db conn.
//validate our q
if (isset($q = $_GET['q'])) {
  $q = sanitize($q); // some sanitize function that's application wide -- use your imagination. 
  $query = "SELECT * FROM product WHERE name like ?";
  $stmt = $this->pdo->prepare($query);
  $stmt->execute(array("%" . $q . "%"));
  $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// no q given



2- adding the table: 

brand: 
===== 

id 
name 

and adding field brand_id to the product table, write a query that returns products by keyword (as in 1) but also the brands name associated IF ANY. 
this is the new query, assume other pieces from above, but this is the core change:
  $query= "select * from product p left join brand b on p.brand_id = b.id where p.name like ?";

3- given the following: 

abstract class A { abstract function() doSomething(); } 
interface B { abstract function() doSomethingElse(); } 

give a simple example on how to use these classes. What is the difference between them and when do you use one of the other? 

// You extend abstract classes, you cannot instantiate them directly, main purpose here is to provide some common functionality among
// the hierarchy in which we use them.
class AB extends A 
{
     public function doSomething()   
     {
         // must define since parent defined it as abstract.
     }
}

// We implement interfaces, gives a way to define a contract as to which methods need to be implemented.
// Provides no actual impl. in the interface itself; all methods are abstract by default.
class BC implements C 
{
     public function doSomethingElse()
     {
         // implement this function.
     }
}



4- what's wrong with this? 

function getBankByLocation($l) 
{ 
    $sql = 'select * from bank where location = ' . $l; 
    // execute $sql 
    // ... 
} 
A few things:
$l is a horrible variable name.
$l, was it sanitized?
Using a parameterized sql statement is safer against sql injection attacks.
This function has a 'get' style signature, but nothing is being returned.
Depending on what $l is that sql statement will not do well since it's not quoting the right side of the =.



5- imagine we have two credit card processing companies in two different countries. Both APIs are similar, but a user with a location in country A will be using credit card company 1 and user in country B will be using the other one. We already know which country the user is in. How would you implement the switch between credit card APIs so that we still have the flexibility to add more APIs later with a minimum of changes to the existing code? 

Depending on how similar those APIs are we can write our own interface to handle it, but we'd want to using some dependency injection to inject the correct processing service underneath so it can contact the correct processor based on the user's country designation.

Or we could have something like a RESTful service and we could hit various versions of the processor service that knows which backend CC processor to hit, so the URLs might differ like so:

/ccprocessor/{$countrycode}/verify



6- in our project, people can wish products in two ways: 

 a. wish a product somebody else added to the database 
 b. adding a new product which creates automatically a wish 

A product is characterized by a name, brand and photo. A wish is a product with a price chosen by a user. The DB structure is as follows: 

Product 
======= 
product_id 
name 
brand_id 
photo 
reference_product_id 

Brand 
===== 
brand_id 
name 

User 
==== 
user_id 
name 

Wish 
==== 
user_id 
product_id 
price 

Products can be browsed in a catalog. To avoid duplicates in the catalog, products are assigned a reference product. Only reference products appear in the catalog. Example: User U1 wishes IPAD with Photo P1, user U2 wishes IPAD with Photo P2 and user U3 re-wishes IPAD with photo P1. We end up with something like this: 

User 
=== 
U1 Robert 
U2 Pam 
U3 Jhon 

Brand 
===== 
B1 Apple 

Product 
======= 
P1 IPad 1 P1.jpg - 
P2 IPAD 1 P2.jpg P1 

Wish 
==== 
U1 P1 500 
U2 P2 540 
U3 P1 600 

You can see that P1 is the reference product in this case, so it doesn't reference anything but itself, and P2 references P1. U1 and U3 wished the same product P1 with different price tags (not important to understand prices at this point). In the catalog, only P1 would show as a reference product. 

NOW, we want to know how many users have wished P1. At first glance, only 2, U1 and U3. But now P2 references P1, so we want to count P2 as well. So total number of wish is 3. We have two options to count the number of wishes: 

1- create a query to count the wishes on P1 AND all the product referencing P1 
2- find a more efficient way to do it, maybe by de-normalizing the data 

Write a mock query for case 1- and propose an alternative 
--------------------------------------------------------- 
1: This query will get you your answer, but with that cartesian product it's gonna be heavy on the results:
select w.*, p.* from wish w, product p where w.product_id = 1 OR p.reference_product_id = 1 group by w.wish_id

2: Alternate way:
You could de-normalize by putting the reference_product_id in the wish table.  Then you'd have something like:
select w.* from wish w where w.product_id = 1 or w.reference_product_id = 1;



7- When a user enters a product name for example, how do you prevent this user to enter malicious code? We only want plain text. 
Have to filter out using sanitizing methods which are usually written with preg_replace to filter out the malicious code like <script> tags, etc.  Can be combined with php's strip_tags as well.


8- How to make sure the pages will display foreign characters properly? 
Have to encode as UTF-8 to see those properly.


9- We are calling a function in PHP that might generate an expected exception but we want to deal with the exception and execute the rest of the code anyway. How do you implement such a thing? 

try {
	// call function that may throw Exception...
} catch (Exception $e) {
       // catch here and continue executing, if completely hosed, throw up the call stack.
}
 


10- When resizing an uploaded image, regardless of the orientation, we want to end up with a square image but want to avoid spaces on the sides. How to efficinely crop this image. Write a quick PHP snippet for that. 

Seems Imagick has the appeal here:
$img  = new Imagick('file.gif');
$img = $img->cropImage($w, $h, $x, $y);
$img->setImagePage(0, 0, 0, 0); // This will apparently remove the blank edges.



11- Given a month as a number, how do I display the name of the month depending on the locale? 
  $month=3;
  echo strftime("%B", strtotime('01-'.$month.'-2013'));


12- How to compare two dates in PHP by day only (ex: 08/01/2012 > 07/12/2012)? 
been doing this a lot recently on the fox project:

  $dt1 = new DateTime('08/01/2012');
  $dt2 = new DateTime('07/13/2012');
  $interval = $dt1->diff($dt2);
  echo $interval->format('%a') . 'days';


13- XMLHttpRequest does not allow execution of code on a domain different from the one calling the script, how to get around that? 
jsonp, used it at Disney when we were moving to new menu on another domain, but had to show it on legacy sites.  Ajax/jsonp combination, worked really well.


14- Name a few method to accelerate the fetching of data in a website backend. 
Big Fast Hardware for the backend. :)

Cache is the best and obvious method, if we can cache queries and results then that'll accelerate the backend.
If we can trim down datasets to the bare minimum that can be helpful.
A NoSQL backend could prove to be an acceleration technique.

