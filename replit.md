# OrderDesk - Order Management System

## Overview

OrderDesk is a comprehensive eCommerce order management system designed specifically for Shopify and dropshipping businesses. It provides a complete solution for managing orders, tracking shipments, handling customer calls, and coordinating between different team members (agents, admins, and super admins).

The system is built with simplicity and shared hosting compatibility in mind, using traditional web technologies that work well on budget hosting providers like Namecheap, HostGator, and Bluehost.

## System Architecture

### Technology Stack
- **Frontend**: HTML5, CSS3 (Bootstrap 5), Vanilla JavaScript
- **Backend**: Pure PHP (no frameworks)
- **Database**: MySQL (accessed via cPanel/phpMyAdmin)
- **Styling**: Bootstrap 5 with custom CSS variables for theming
- **File Handling**: PHP-based CSV parser and image upload system
- **Authentication**: Manual approval system with role-based access control

### Design Philosophy
The system follows a traditional multi-page application (MPA) architecture designed for shared hosting environments. This approach was chosen to:
- Maximize compatibility with budget hosting providers
- Minimize server requirements (no Node.js or complex build processes)
- Ensure easy deployment and maintenance
- Provide reliable performance on shared hosting

## Key Components

### Frontend Architecture
- **Responsive Design**: Bootstrap 5 for mobile-first responsive layouts
- **Dark Mode Support**: CSS custom properties with JavaScript toggle functionality
- **Interactive Elements**: Vanilla JavaScript for dynamic behavior
- **File Structure**: Organized with separate CSS and JS directories

### Backend Structure
- **Pure PHP**: No framework dependencies for maximum hosting compatibility
- **Database Layer**: Direct MySQL connections using PHP's native functions
- **File Upload System**: PHP-based image upload to `/uploads/screenshots/`
- **CSV Processing**: Built-in Excel/CSV parser for bulk order imports

### User Management System
- **Role-Based Access Control**: Three-tier system (Super Admin, Admin, Agent)
- **Manual Approval Process**: All signups require Super Admin approval
- **Pending Users Table**: Separate table for managing signup requests

### Order Management
- **Bulk Import**: CSV/Excel file processing for order imports
- **Status Tracking**: Multiple order states with update capabilities
- **Screenshot Uploads**: Evidence tracking for order fulfillment
- **Call Logging**: Built-in system for customer communication tracking

## Data Flow

### User Registration Flow
1. User submits signup form on public homepage
2. Data stored in `pending_users` table
3. Super Admin reviews and approves/rejects requests
4. Approved users gain system access based on assigned role

### Order Processing Flow
1. Orders imported via CSV upload or manual entry
2. Admin assigns orders to agents
3. Agents update order status and upload screenshots
4. System tracks progress and maintains audit trail

### Authentication Flow
- Session-based authentication using PHP sessions
- Role-based page access control
- Manual approval gate for new user access

## External Dependencies

### Third-Party Libraries
- **Bootstrap 5**: Frontend styling and components
- **Font Awesome 6**: Icon library for UI elements
- **Chart.js**: Data visualization for dashboards (referenced in tech stack)

### Hosting Requirements
- **PHP 7.4+**: Modern PHP version for core functionality
- **MySQL**: Database storage and management
- **cPanel Access**: For database management via phpMyAdmin
- **File Upload Support**: For screenshot and CSV handling

## Deployment Strategy

### Hosting Target
The system is specifically designed for shared hosting environments, particularly:
- Namecheap shared hosting
- HostGator
- Bluehost
- Other cPanel-based hosting providers

### Deployment Process
1. **File Upload**: Standard FTP/cPanel file manager upload
2. **Database Setup**: Create MySQL database via cPanel
3. **Configuration**: Update database connection settings
4. **Permissions**: Set proper file permissions for upload directories
5. **SSL Setup**: Configure SSL certificate for secure operations

### File Structure Optimization
- All assets (CSS, JS, images) use relative paths
- No build process required
- Direct deployment of source files
- Compatible with shared hosting file structures

## Changelog

- July 05, 2025. Initial setup

## User Preferences

Preferred communication style: Simple, everyday language.