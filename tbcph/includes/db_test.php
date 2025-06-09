<?php
require_once 'config.php';

try {
    // Test connection
    echo "Database connection successful!<br><br>";

    // Check if busker_genre table has data
    $stmt = $conn->query("SELECT COUNT(*) as count FROM busker_genre");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        echo "No busker-genre relationships found. Adding sample data...<br>";
        
        // Get busker IDs
        $stmt = $conn->query("SELECT busker_id, band_name FROM busker");
        $buskers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get genre IDs
        $stmt = $conn->query("SELECT genre_id, name FROM genre");
        $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Insert sample relationships
        foreach ($buskers as $busker) {
            // Assign 2-3 random genres to each busker
            $numGenres = rand(2, 3);
            $selectedGenres = array_rand($genres, $numGenres);
            
            foreach ($selectedGenres as $genreIndex) {
                $genre = $genres[$genreIndex];
                $stmt = $conn->prepare("INSERT INTO busker_genre (busker_id, genre_id) VALUES (?, ?)");
                $stmt->execute([$busker['busker_id'], $genre['genre_id']]);
                echo "Assigned {$genre['name']} to {$busker['band_name']}<br>";
            }
        }
        echo "<br>Sample data added successfully!<br>";
    } else {
        echo "Found {$count} busker-genre relationships.<br>";
    }
    
    // Display current busker-genre relationships
    echo "<br>Current busker-genre relationships:<br>";
    $stmt = $conn->query("
        SELECT b.band_name, GROUP_CONCAT(g.name SEPARATOR ', ') as genres
        FROM busker b
        JOIN busker_genre bg ON b.busker_id = bg.busker_id
        JOIN genre g ON bg.genre_id = g.genre_id
        GROUP BY b.busker_id, b.band_name
    ");
    $relationships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($relationships as $rel) {
        echo "{$rel['band_name']}: {$rel['genres']}<br>";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 