<?php

namespace MIMA\Controllers;

use MIMA\Models\Product;

class ProductController
{
    private $productModel;
    
    public function __construct()
    {
        $this->productModel = new Product();
    }
    
    public function create($user_id, $data)
    {
        if (!isset($data->name) || !isset($data->selling_price)) {
            return ['success' => false, 'message' => 'Product name and selling price are required'];
        }
        
        $quantity = isset($data->quantity) ? $data->quantity : 0;
        $cost_price = isset($data->cost_price) ? $data->cost_price : 0;
        $reorder_level = isset($data->reorder_level) ? $data->reorder_level : 10;
        
        $result = $this->productModel->create(
            $user_id,
            $data->name,
            $data->category ?? '',
            $quantity,
            $data->selling_price,
            $cost_price,
            $reorder_level
        );
        
        if ($result) {
            return ['success' => true, 'message' => 'Product created successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to create product'];
    }
    
    public function getAll($user_id)
    {
        $products = $this->productModel->getAll($user_id);
        return ['success' => true, 'products' => $products];
    }
    
    public function getOne($user_id, $id)
    {
        $product = $this->productModel->getById($id, $user_id);
        if ($product) {
            return ['success' => true, 'product' => $product];
        }
        return ['success' => false, 'message' => 'Product not found'];
    }
    
    public function update($user_id, $id, $data)
    {
        $product = $this->productModel->getById($id, $user_id);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        $name = isset($data->name) ? $data->name : $product['name'];
        $category = isset($data->category) ? $data->category : $product['category'];
        $quantity = isset($data->quantity) ? $data->quantity : $product['quantity'];
        $selling_price = isset($data->selling_price) ? $data->selling_price : $product['selling_price'];
        $cost_price = isset($data->cost_price) ? $data->cost_price : $product['cost_price'];
        $reorder_level = isset($data->reorder_level) ? $data->reorder_level : $product['reorder_level'];
        
        $result = $this->productModel->update($id, $user_id, $name, $category, $quantity, $selling_price, $cost_price, $reorder_level);
        
        if ($result) {
            return ['success' => true, 'message' => 'Product updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update product'];
    }
    
    public function delete($user_id, $id)
    {
        $result = $this->productModel->delete($id, $user_id);
        if ($result) {
            return ['success' => true, 'message' => 'Product deleted successfully'];
        }
        return ['success' => false, 'message' => 'Failed to delete product'];
    }
}