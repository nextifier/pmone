# VS Code Setup untuk Laravel 12 Development

Setup ini dirancang khusus untuk mengoptimalkan pengalaman development Laravel 12 dengan VS Code.

## 🚀 Extension yang Direkomendasikan

### PHP & Laravel Core
- **bmewburn.vscode-intelephense-client** - PHP IntelliSense yang powerful
- **open-southeners.laravel-pint** - Laravel Pint formatter
- **shufo.vscode-blade-formatter** - Blade template formatter
- **ryannaddy.laravel-artisan** - Laravel Artisan commands
- **codingyu.laravel-goto-view** - Quick navigation ke views
- **amiralizadeh9480.laravel-extra-intellisense** - Extra autocomplete untuk Laravel

### Blade Template Engine
- **cjhowe7.laravel-blade** - Blade syntax highlighting
- **austenc.laravel-blade-spacer** - Auto spacing untuk Blade

### Database
- **mtxr.sqltools** - SQL tools
- **mtxr.sqltools-driver-pg** - PostgreSQL driver

### Productivity
- **formulahendry.auto-close-tag** - Auto close HTML tags
- **formulahendry.auto-rename-tag** - Auto rename paired tags
- **usernamehw.errorlens** - Inline error display
- **bradlc.vscode-tailwindcss** - Tailwind CSS IntelliSense
- **rangav.vscode-thunder-client** - API testing

## 🛠️ Fitur yang Sudah Dikonfigurasi

### 1. Auto-formatting dengan Laravel Pint
- Format otomatis saat save file
- Konsisten dengan Laravel coding standards

### 2. Intelephense Optimization
- Configured untuk Laravel 12
- Support untuk `auth()->check()`, `auth()->id()`, dll
- Enhanced autocomplete untuk semua Laravel helpers

### 3. Laravel IDE Helper
- Auto-generated model documentation
- Method autocomplete untuk Eloquent
- Facade autocomplete
- Command: `composer run ide-helper` untuk regenerate

### 4. Blade Template Support
- Syntax highlighting
- Emmet support
- Auto-complete untuk Laravel directives

### 5. Tailwind CSS Integration
- IntelliSense untuk Tailwind classes
- Autocomplete dalam Blade files

## ⌨️ Keyboard Shortcuts

- `Cmd+Shift+T` - Run Laravel Tests
- `Cmd+Shift+S` - Start Laravel Server
- `Cmd+Shift+C` - Clear Laravel Cache
- `Cmd+Shift+P` - Run Laravel Pint
- `Cmd+Shift+I` - Generate IDE Helper files

## 📋 Available Tasks (Cmd+Shift+P → Tasks: Run Task)

- **Laravel: Serve** - Start development server
- **Laravel: Queue Work** - Start queue worker
- **Laravel: Run Tests** - Run Pest tests
- **Laravel: Clear Cache** - Clear all Laravel caches
- **Laravel: Generate IDE Helper** - Regenerate helper files
- **Laravel: Run Pint** - Format code dengan Pint
- **NPM: Dev** - Start Vite development
- **Composer: Dev** - Start all services (server, queue, logs, vite)

## 🔧 Configuration Files

### `.vscode/settings.json`
- Intelephense optimization
- File associations
- Auto-formatting rules
- Exclusions untuk performa

### `.vscode/extensions.json`
- Recommended extensions
- Unwanted extensions yang conflict

### `.vscode/tasks.json`
- Laravel-specific tasks
- Build dan test commands

### `.vscode/launch.json`
- PHP debugging configuration
- Xdebug setup

## 🐛 Debugging Setup

1. Install Xdebug PHP extension
2. Configure PHP with Xdebug
3. Use launch configurations:
   - **Listen for Xdebug** - Untuk web debugging
   - **Launch currently open script** - Untuk script debugging
   - **Launch Artisan Command** - Untuk Artisan debugging

## 📦 Composer Scripts

```bash
# Generate IDE helper files
composer run ide-helper

# Run development server dengan semua services
composer run dev

# Run tests
composer run test
```

## 🔍 Troubleshooting

### Issue: `auth()->check()` shows "Undefined method"
**Solution**: File `_ide_helper.php` sudah di-generate. Restart VS Code atau PHP Language Server.

### Issue: Blade files tidak syntax highlighting
**Solution**:
1. Install extension `cjhowe7.laravel-blade`
2. Restart VS Code
3. File association sudah dikonfigurasi otomatis

### Issue: Tailwind classes tidak autocomplete
**Solution**:
1. Install extension `bradlc.vscode-tailwindcss`
2. Extension akan auto-detect Tailwind config

### Issue: Laravel Pint tidak format otomatis
**Solution**:
1. Install extension `open-southeners.laravel-pint`
2. Setting `formatOnSave` sudah enabled
3. Manual format: `Cmd+Shift+P`

## 🚀 Performance Tips

1. **Exclude directories** dari indexing sudah dikonfigurasi:
   - `/vendor`
   - `/node_modules`
   - `/storage/framework`
   - `/bootstrap/cache`

2. **File watcher optimization** untuk mengurangi CPU usage

3. **Intelephense max file size** sudah di-set ke 5MB

## 📂 File Structure

```
.vscode/
├── settings.json          # Main VS Code settings
├── extensions.json        # Recommended extensions
├── tasks.json            # Laravel tasks
├── launch.json           # Debug configurations
└── keybindings.json      # Custom keyboard shortcuts

# Auto-generated (gitignored)
_ide_helper.php           # Laravel facades & helpers
_ide_helper_models.php    # Model documentation
.phpstorm.meta.php        # IDE metadata
```

## 🎯 Tips Penggunaan

1. **Quick file navigation**: `Cmd+P` lalu ketik nama file
2. **Symbol search**: `Cmd+Shift+O` untuk cari method/property dalam file
3. **Workspace symbol**: `Cmd+T` untuk cari symbol di seluruh project
4. **Go to definition**: `Cmd+Click` atau `F12`
5. **Peek definition**: `Alt+F12`
6. **Find references**: `Shift+F12`

## 🔄 Maintenance

### Weekly
- `composer run ide-helper` - Update IDE helper files jika ada model baru

### After Package Updates
- `composer run ide-helper` - Regenerate helpers
- Restart VS Code untuk refresh IntelliSense

### Git
- `.vscode/settings.json` dan `.vscode/extensions.json` di-commit
- IDE helper files di-gitignore

---

Setup ini dirancang untuk memberikan pengalaman development Laravel yang optimal dengan autocompletion, debugging, dan productivity features yang lengkap.