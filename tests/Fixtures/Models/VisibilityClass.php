<?php
namespace WpMVC\Container\Tests\Fixtures\Models;

class VisibilityClass {
    private function private_method() {
        return 'private'; }
 
    protected function protected_method() {
        return 'protected'; }
 
    public function public_method() {
        return 'public'; }
}
