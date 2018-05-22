<?php
/**
 * Description
 *
 * Copy the product reviews from one product to another.
 */

/**
 * How To Use
 *    1. Run this script via PHP in the command line.
 *         php copy_product_reviews_to_product.php (int)old_product_id (int)new_product_id (string)server_name(not required on production)
 *         Example: php copy_product_reviews_to_product.php 2065 7232 /var/www/vhosts/mydomain.com/html/
 *    2. Clear the full page cache
 */

/**
 * Undo an accidential copy to Invalid Product ID
 *   If you ran this with the new product ID that is non-existant, we can easilly delete all reviews from that non-existant product ID.
 *     DELETE FROM review_detail WHERE review_id IN ( SELECT review_id FROM rating_option_vote WHERE entity_pk_value = 210777 );
 *     DELETE FROM review_store WHERE review_id IN ( SELECT review_id FROM rating_option_vote WHERE entity_pk_value = 210777 );
 *     DELETE FROM rating_option_vote WHERE entity_pk_value = 210777;
 *     DELETE FROM review WHERE entity_pk_value = 210777;
 */

/**
 * Details
 *
 * There following tables are involved in correctly displaying a review on a product.
 *   review
 *     - Review date/time, status, and prodcut relationship
 *   review_detail
 *     - Review contents
 *   review_store
 *     - Correlation between review_id and store to display on
 *   rating_option_vote
 *     - Rating for the review as well as IP address
 *
 * There are a few other tables associated to this (rating_* and review_*), but one of note is rating_option_vote_aggregated.
 *   That table holds the aggregated rating of the review.  This gets calculated each time a review for a product is saved/added/etc.
 *   Magento handles calculating the information this table, but it's handy to know it's there.
 */


/**
 * Definitions
 */
$NL = "\n";
$DS = "/";
$errors = array();
$success = 0;
$serverName = false;
$mageInstallDir = false;
$credentials = array();


/**
 * Paramters
 */
if( !count( $argv ) == 4 ) {
	$errors[] = "Missing / Invalid arguments";
} else {
	$oldProdId = intval( $argv[1] );
	$newProdId = intval( $argv[2] );
	$mageInstallDir = $argv[3];
}


/**
 * Error Checking
 */
if( !is_int( $oldProdId ) ) {
	$errors[] = "Invalid old product id";
}

if( !is_int( $newProdId ) ) {
	$errors[] = "Invalid new product id";
}


/**
 * DB Connection
 */
$mageConfig = $mageInstallDir . "app" . $DS . "etc" . $DS . "local.xml";
if( file_exists( $mageConfig ) ) {
	$xml = simplexml_load_file( $mageConfig );
	if( $xml ) {
		$credentials['mysql']['servername'] = $xml->global->resources->default_setup->connection->host;
		$credentials['mysql']['username'] = $xml->global->resources->default_setup->connection->username;
		$credentials['mysql']['password'] = $xml->global->resources->default_setup->connection->password;
		$credentials['mysql']['dbname'] = $xml->global->resources->default_setup->connection->dbname;
	}
} else {
	$errors[] = "Mage configuration file doesn't exist: " . $mageConfig;
}


/**
 * Process Copy
 */
if( count( $errors ) == 0 ) {
	echo "Start reviews copy" . $NL;

	// Create connection
	$conn = new mysqli( $credentials["mysql"]["servername"], $credentials["mysql"]["username"], $credentials["mysql"]["password"], $credentials["mysql"]["dbname"] );

	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	} 
	echo "Connected successfully" . $NL . $NL;

	echo "Copying reviews from product entity_id " . $oldProdId . " to product entity_id " . $newProdId . $NL;

	/* Get stores */
	$qStores = "SELECT store_id FROM core_store";
	$stores = $conn->query($qStores);

	/* Get Reviews */
	$qSourceReviews = "SELECT * FROM review WHERE entity_pk_value = " . $oldProdId;
	$result = $conn->query($qSourceReviews);
	echo "Number of reviews: " . $result->num_rows . $NL;

	/* Process Reviews */
	while ( $row = $result->fetch_assoc() ) {
		echo "  Review ID: " . $row['review_id'] . $NL;

		/* Get old product review detail */
		$qSourceReviewDetails = "SELECT * FROM review_detail WHERE review_id = " . $row['review_id'];
		$resultSingle = $conn->query($qSourceReviewDetails);
		$reviewDetail = $resultSingle->fetch_array() ;
		echo "    Review: detail_id: " . $reviewDetail['detail_id'] . "  |  review_id: " . $reviewDetail['review_id'] . "  |  title: " . $reviewDetail['title'] . "  |  nickname: " . $reviewDetail['nickname'] . $NL;

		$qCheckReviewExists = "SELECT COUNT(*) AS count FROM review AS r LEFT JOIN review_detail AS d ON r.review_id = d.review_id WHERE r.entity_pk_value = " . $newProdId . " AND d.title = '" . addslashes( $reviewDetail['title'] ) . "' AND d.detail = '" . addslashes( $reviewDetail['detail'] ) . "' AND d.nickname = '" . addslashes( $reviewDetail['nickname'] ) . "';";
		$resultCheck = $conn->query( $qCheckReviewExists );
		$checkData = $resultCheck->fetch_array();
		if( isset( $checkData['count'] ) && $checkData['count'] > 0 ) {
			echo "      INFO: This review already exists for this product.  Skipping this review." . $NL;
			continue;
		}

		/* Add new review entry */
		$qInsertReview = "INSERT INTO review (created_at, entity_id, entity_pk_value, status_id) VALUES ('" . $row['created_at'] . "', " . $row['entity_id'] . ", " . $newProdId . ", " . $row['status_id'] . ")";
		$insert = $conn->query($qInsertReview);
		if( !$insert ) {
			echo "ERROR!!!" . $NL;
			echo $qInsertReview . $NL;
			var_dump($conn);
			var_dump($insert);
		}
		$newReviewId = $conn->insert_id;
		echo "    New Review ID: " . $newReviewId . $NL;

		/* Add new review_detail entry */
		$qInsertReviewDetail = "INSERT INTO review_detail (review_id, store_id, title, detail, nickname, customer_id, recommend) VALUES (" . $newReviewId . ", " . $reviewDetail['store_id'] . ", '" . addslashes($reviewDetail['title']) . "', '".addslashes($reviewDetail['detail']) . "', '" . addslashes($reviewDetail['nickname']) . "', " . ($reviewDetail['customer_id'] ? $reviewDetail['customer_id'] : 'NULL' ) . ", " . ( isset( $reviewDetail['recommend'] ) ? $reviewDetail['recommend'] : 'NULL' ) . ")";
		$insert = $conn->query($qInsertReviewDetail);
		if( !$insert ) {
			echo "ERROR!!!" . $NL;
			echo $qInsertReviewDetail . $NL;
			var_dump($conn);
			var_dump($insert);
		}

		/* Add rating table entry */
		$qSourceRating = "SELECT * FROM rating_option_vote WHERE entity_pk_value = " . $oldProdId . " AND review_id = " . $reviewDetail['review_id'];
		$rating = $conn->query($qSourceRating);
		while ( $ratingRow = $rating->fetch_assoc() ) {
			$qInsertRating = "INSERT INTO rating_option_vote (option_id, remote_ip, remote_ip_long, customer_id, entity_pk_value, rating_id, review_id, percent, value) VALUES (" . $ratingRow['option_id'] . ", '" . $ratingRow['remote_ip'] . "', '" . addslashes($ratingRow['remote_ip_long'] ) . "', " . ( $reviewDetail['customer_id'] ? $reviewDetail['customer_id']: 'NULL' ) . ", " . $newProdId . ", " . $ratingRow['rating_id'] . ", " . $newReviewId . ", " . $ratingRow['percent'] . ", " . $ratingRow['value'] . ")";
			$insert = $conn->query($qInsertRating);
			if( !$insert ) {
				echo "ERROR!!!" . $NL;
				echo $qInsertRating . $NL;
				var_dump($conn);
				var_dump($insert);
			}
		}

		/* Add review_store entries with new review id */
		while ( $store = $stores->fetch_assoc() ) {
			$qInsertReviewStore = "INSERT INTO review_store (review_id, store_id) VALUES (" . $newReviewId . ", " . $store['store_id'] . ")";
			$insert = $conn->query($qInsertReviewStore);
			if( !$insert ) {
				echo "ERROR!!!" . $NL;
				echo $qInsertReviewStore . $NL;
				var_dump($conn);
				var_dump($insert);
			}
		}
		mysqli_data_seek($stores,0);  // reset pointer

		$success += 1;
		echo "    Review Copied  |  Old Review ID: " . $reviewDetail['review_id'] . "  |  New Review ID: " . $newReviewId . $NL;
	}

	echo "Review Copy Process Complete" . $NL;
	echo "  " . $success . " Reviews (of " . $result->num_rows . ") Copied " . $NL;


	/**
	 * Aggregate data
	 */
	if( $success > 0 ) {
		$mageFilename = $mageInstallDir . "app/Mage.php";
		require_once $mageFilename;
		Mage::setIsDeveloperMode(true);
		ini_set('display_errors', 1);
		umask(0);
		Mage::app();

		if( $newReviewId ) {
			$review = Mage::getModel('review/review')->load($newReviewId);
			$review->aggregate();
			echo "  Review Aggregate data has been updated." . $NL . $NL;
		} else {
			echo "ERROR: can't update aggreate data becuase newReviewId not set.  Save a review from this product to correct update the aggreate info." . $NL;
		}
	}

	echo $NL . "++ Operation complete ++" . $NL . $NL;
} else {
	echo "!! No action taken due to errors !!" . $NL;
	foreach( $errors as $error ) {
		echo "   ERROR: " . $error . $NL;
	}

}

echo $NL;
?>
