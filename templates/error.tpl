<!-- Structure mimics existing mediawiki error boxes -->
<div class="bugzilla errorbox">
    <h2>Bugzilla query error</h2>
    <p>
        <?php echo htmlspecialchars(is_string($error) ? $error : print_r($error, true)); ?>
    </p>
</div>
