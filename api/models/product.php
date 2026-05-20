<?php

namespace MIMA\Models;

use MIMA\Config\Database;
use PDO;

class Product
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($user_id, $name, $category, $quantity, $selling_price, $cost_price, $reorder_level = 10)
    {
        $stmt = $this->db->prepare("
            INSERT INTO products (user_id, name, category, quantity, selling_price, cost_price, reorder_level) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $name, $category, $quantity, $selling_price, $cost_price, $reorder_level]);
    }
    
    public function getAll($user_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getById($id, $user_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function update($id, $user_id, $name, $category, $quantity, $selling_price, $cost_price, $reorder_level)
    {
        $stmt = $this->db->prepare("
            UPDATE products 
            SET name = ?, category = ?, quantity = ?, selling_price = ?, cost_price = ?, reorder_level = ? 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$name, $category, $quantity, $selling_price, $cost_price, $reorder_level, $id, $user_id]);
    }
    
    public function delete($id, $user_id)
    {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $user_id]);
    }
}