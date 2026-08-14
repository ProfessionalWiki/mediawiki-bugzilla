<?php
    echo "<span class='bugzilla-field-" . htmlspecialchars($field) . "'>";
    if( isset( $bug[$field] ) && is_array($bug[$field]) ) {
        echo htmlspecialchars(implode(', ', $bug[$field]));
    }elseif(isset($bug[$field]) ) {
        echo htmlspecialchars($bug[$field]);
    }else{
        echo "No " . htmlspecialchars($field);
    }
    echo "</span>";
?>
