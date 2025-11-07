# Документация для разработчиков "Таргет"

API для работы с контактами и компаниями в Битрикс24 CRM. Позволяет создавать, искать, обновлять и **удалять** контакты и компании с фильтрацией по компаниям.

## 📋 Содержание

- [Установка](#установка)
- [Базовое использование](#базовое-использование)
- [API методы](#api-методы)
  - [Методы для контактов](#методы-для-контактов)
  - [Методы для компаний](#методы-для-компаний)
- [Примеры кода](#примеры-кода)
- [Обработка ошибок](#обработка-ошибок)
- [FAQ](#faq)

## 🚀 Установка

### 1. Разместите файлы

**Класс FindContact:**
```
/local/modules/leadspace.integrationtarget/lib/classes/FindContact.php
```

**Класс CompanyManager:**
```
/local/modules/leadspace.integrationtarget/lib/classes/CompanyManager.php
```

**API хандлер:**
```
/local/ajax/handler.php
```

### 2. Структура модуля

Убедитесь что ваш модуль имеет правильную структуру:
```
/local/modules/leadspace.integrationtarget/
├── include.php
└── lib/
    └── classes/
        ├── FindContact.php
        └── CompanyManager.php
```

### 3. Права доступа

Убедитесь что веб-сервер имеет права на чтение файлов модуля.

## 🔧 Базовое использование

### JavaScript (Fetch API)

```javascript
async function createContact() {
    const response = await fetch('/local/ajax/handler.php', {
        method: 'POST',
        body: new URLSearchParams({
            action: 'findOrCreateContact',
            'params[properties][NAME]': 'Иван Петров',
            'params[properties][PHONE]': '+7 999 123-45-67',
            'params[properties][EMAIL]': 'ivan@example.com',
            'params[properties][COMPANY_ID]': '1'
        })
    });
    
    const result = await response.json();
    console.log(result);
}
```

## 📡 API методы

### Методы для контактов

#### 1. findOrCreateContact

Находит существующий контакт или создает новый.

**Параметры:**
```javascript
{
    action: 'findOrCreateContact',
    params: {
        properties: {
            NAME: string,           // Имя (обязательно если нет FIO)
            FIO: string,            // ФИО (альтернатива NAME)
            LAST_NAME: string,      // Фамилия (опционально)
            PHONE: string,          // Телефон (обязательно если нет EMAIL)
            EMAIL: string,          // Email (обязательно если нет PHONE)
            COMPANY_ID: number      // ID компании (обязательно)
        }
    }
}
```

**Ответ при успехе:**
```json
{
    "success": true,
    "data": {
        "contactId": 123
    }
}
```

#### 2. updateContact

Обновляет данные существующего контакта.

**Параметры:**
```javascript
{
    action: 'updateContact',
    params: {
        contactId: number,      // ID контакта
        data: {
            NAME: string,       // Новое имя
            LAST_NAME: string,  // Новая фамилия
            COMMENTS: string,   // Комментарий
            // ... другие поля контакта
        }
    }
}
```

**Ответ:**
```json
{
    "success": true,
    "data": {
        "contactId": 123
    }
}
```

#### 3. deleteContact ⚠️

**Удаляет контакт из CRM.**

**Параметры:**
```javascript
{
    action: 'deleteContact',
    params: {
        contactId: number      // ID контакта для удаления
    }
}
```

**Ответ при успехе:**
```json
{
    "success": true,
    "data": {
        "message": "Контакт успешно удален",
        "contactId": 123
    }
}
```

**Ответ при ошибке:**
```json
{
    "success": false,
    "error": "Не удалось удалить контакт"
}
```

### Методы для компаний

#### 1. findOrCreateCompany

Находит существующую компанию или создает новую.

**Параметры:**
```javascript
{
    action: 'findOrCreateCompany',
    params: {
        properties: {
            TITLE: string,      // Название компании (обязательно)
            PHONE: string,      // Телефон (опционально)
            EMAIL: string,      // Email (опционально)
            NAME: string        // Контактное лицо (опционально)
        }
    }
}
```

**Ответ:**
```json
{
    "success": true,
    "data": {
        "companyId": 456
    }
}
```

#### 2. updateCompany

Обновляет данные существующей компании.

**Параметры:**
```javascript
{
    action: 'updateCompany',
    params: {
        companyId: number,  // ID компании (обязательно)
        data: {             // Поля для обновления
            TITLE: string,
            COMMENTS: string
            // Другие поля CRM компании
        }
    }
}
```

#### 3. deleteCompany ⚠️

**Удаляет компанию из CRM.**

**Параметры:**
```javascript
{
    action: 'deleteCompany',
    params: {
        companyId: number      // ID компании для удаления
    }
}
```

**Ответ при успехе:**
```json
{
    "success": true,
    "data": {
        "message": "Компания успешно удалена",
        "companyId": 456
    }
}
```

**Ответ при ошибке:**
```json
{
    "success": false,
    "error": "Не удалось удалить компанию"
}
```

#### 4. createRequisites

Создает или обновляет реквизиты компании.

**Параметры:**
```javascript
{
    action: 'createRequisites',
    params: {
        companyId: number,  // ID компании (обязательно)
        requisites: {       // Реквизиты
            INN: string,
            KPP: string,
            OGRN: string,
            ADDRESS: string,
            PHONE: string,
            EMAIL: string,
            CONTACT_PERSON: string,
            RESPONSIBLE_PERSON: string,
            COMMENT: string
        }
    }
}
```

## 💡 Примеры кода

### Удаление контакта

```javascript
async function deleteContactById(contactId) {
    try {
        const response = await fetch('/local/ajax/handler.php', {
            method: 'POST',
            body: new URLSearchParams({
                action: 'deleteContact',
                'params[contactId]': contactId
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Контакт удален:', result.data.contactId);
            return true;
        } else {
            console.error('Ошибка удаления:', result.error);
            return false;
        }
    } catch (error) {
        console.error('Сетевая ошибка:', error);
        return false;
    }
}

// Использование
await deleteContactById(123);
```

### Удаление компании

```javascript
async function deleteCompanyById(companyId) {
    try {
        const response = await fetch('/local/ajax/handler.php', {
            method: 'POST',
            body: new URLSearchParams({
                action: 'deleteCompany',
                'params[companyId]': companyId
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Компания удалена:', result.data.companyId);
            return true;
        } else {
            console.error('Ошибка удаления:', result.error);
            return false;
        }
    } catch (error) {
        console.error('Сетевая ошибка:', error);
        return false;
    }
}

// Использование
await deleteCompanyById(456);
```

### Создание контакта с проверкой

```javascript
async function createContactSafely(contactData) {
    try {
        const formData = new FormData();
        formData.append('action', 'findOrCreateContact');
        
        // Добавляем свойства контакта
        Object.entries(contactData).forEach(([key, value]) => {
            formData.append(`params[properties][${key}]`, value);
        });
        
        const response = await fetch('/local/ajax/handler.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Контакт создан/найден:', result.data.contactId);
            return result.data.contactId;
        } else {
            console.error('Ошибка API:', result.error);
            return null;
        }
    } catch (error) {
        console.error('Сетевая ошибка:', error);
        return null;
    }
}

// Использование
const contactId = await createContactSafely({
    NAME: 'Петр',
    LAST_NAME: 'Сидоров',
    PHONE: '+7 999 888-77-66',
    EMAIL: 'petr@company.com',
    COMPANY_ID: '1'
});
```

### Массовое удаление контактов

```javascript
async function bulkDeleteContacts(contactIds) {
    const results = [];
    
    for (const contactId of contactIds) {
        try {
            const result = await deleteContactById(contactId);
            results.push({
                contactId,
                success: result
            });
            
            // Пауза между запросами
            await new Promise(resolve => setTimeout(resolve, 200));
        } catch (error) {
            results.push({
                contactId,
                error: error.message,
                success: false
            });
        }
    }
    
    return results;
}

// Использование
const contactsToDelete = [123, 124, 125];
const results = await bulkDeleteContacts(contactsToDelete);
console.log('Результаты удаления:', results);
```

### Комплексный пример: Поиск, обновление и удаление

```javascript
async function manageContact(phone) {
    // 1. Найти или создать контакт
    const searchResult = await fetch('/local/ajax/handler.php', {
        method: 'POST',
        body: new URLSearchParams({
            action: 'findOrCreateContact',
            'params[properties][PHONE]': phone,
            'params[properties][NAME]': 'Временный контакт',
            'params[properties][COMPANY_ID]': '1'
        })
    });
    
    const searchData = await searchResult.json();
    
    if (!searchData.success) {
        console.error('Контакт не найден');
        return false;
    }
    
    const contactId = searchData.data.contactId;
    console.log('Найден контакт:', contactId);
    
    // 2. Обновить данные контакта
    const updateResult = await fetch('/local/ajax/handler.php', {
        method: 'POST',
        body: new URLSearchParams({
            action: 'updateContact',
            'params[contactId]': contactId,
            'params[data][NAME]': 'Обновленное имя',
            'params[data][COMMENTS]': 'Контакт обновлен'
        })
    });
    
    const updateData = await updateResult.json();
    console.log('Контакт обновлен:', updateData);
    
    // 3. Удалить контакт
    const deleteResult = await fetch('/local/ajax/handler.php', {
        method: 'POST',
        body: new URLSearchParams({
            action: 'deleteContact',
            'params[contactId]': contactId
        })
    });
    
    const deleteData = await deleteResult.json();
    console.log('Контакт удален:', deleteData);
    
    return deleteData.success;
}

// Использование
await manageContact('+7 999 123-45-67');
```

### PHP пример удаления

```php
<?php
function deleteContact($contactId) {
    $data = [
        'action' => 'deleteContact',
        'params' => [
            'contactId' => $contactId
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://your-domain.com/local/ajax/handler.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);
    
    return $result;
}

// Использование
$result = deleteContact(123);
if ($result['success']) {
    echo "Контакт успешно удален\n";
} else {
    echo "Ошибка: " . $result['error'] . "\n";
}
?>
```

## ⚠️ Обработка ошибок

### Типы ошибок

1. **Сетевые ошибки** - проблемы с подключением
2. **Ошибки валидации** - неверные параметры
3. **Ошибки CRM** - проблемы с Битрикс24
4. **Ошибки модуля** - модуль не найден или не загружен
5. **Ошибки удаления** - не удалось удалить сущность (возможно, она используется)

### Пример обработки

```javascript
async function safeAPICall(action, params) {
    try {
        const response = await fetch('/local/ajax/handler.php', {
            method: 'POST',
            body: new URLSearchParams({
                action,
                ...Object.entries(params).reduce((acc, [key, value]) => {
                    if (typeof value === 'object') {
                        Object.entries(value).forEach(([subKey, subValue]) => {
                            acc[`params[${key}][${subKey}]`] = subValue;
                        });
                    } else {
                        acc[`params[${key}]`] = value;
                    }
                    return acc;
                }, {})
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Unknown API error');
        }

        return result;

    } catch (error) {
        console.error('API Error:', error.message);
        
        // Можно добавить уведомления пользователю
        if (error.message.includes('Module not found')) {
            alert('Модуль CRM не установлен');
        } else if (error.message.includes('HTTP 500')) {
            alert('Ошибка сервера. Попробуйте позже');
        } else if (error.message.includes('удалить')) {
            alert('Не удалось удалить: возможно, сущность используется');
        } else {
            alert('Произошла ошибка: ' + error.message);
        }
        
        return null;
    }
}
```

## 🔍 Особенности

### Поиск по телефону

API автоматически обрабатывает различные форматы телефонов:

- `+7 999 123-45-67`
- `8 (999) 123-45-67`
- `79991234567`
- `89991234567`
- `+79991234567`
- `7 999 123 45 67`

### Фильтрация по компании

API ищет контакты **только** с заполненным `COMPANY_ID`.

### Удаление сущностей

⚠️ **Важно:** 
- Удаление безвозвратно
- Проверяйте ID перед удалением
- Удаление компании не удаляет связанные контакты автоматически
- При удалении контакта связь с компанией разрывается

## ❓ FAQ

### Q: Что делать если контакт не создается?

A: Проверьте:
1. Передан ли телефон или email
2. Указан ли COMPANY_ID
3. Загружен ли модуль CRM
4. Есть ли права на создание контактов

### Q: Можно ли отменить удаление?

A: Нет, удаление безвозвратно. Битрикс24 не имеет встроенной корзины для удаленных контактов и компаний через API.

### Q: Что происходит при удалении компании?

A: Удаляется только компания. Связанные контакты остаются в системе, но теряют связь с этой компанией.

### Q: Нужны ли особые права для удаления?

A: Да, пользователь должен иметь права на удаление контактов/компаний в CRM Битрикс24.

### Q: Как найти контакт без создания нового?

A: API всегда сначала ищет существующий контакт. Если он найден - возвращается его ID без создания дубликата.

### Q: Можно ли искать контакты без привязки к компании?

A: Нет, API специально фильтрует только контакты с заполненным COMPANY_ID.

## 🔧 Отладка

Для отладки проблем:

1. Проверьте логи PHP: `/var/log/php_errors.log`
2. Проверьте логи CompanyManager: `lib/classes/company_manager.txt`
3. Включите отладку в коде:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```
4. Используйте тестовый интерфейс для проверки работы API
5. Проверьте права доступа к файлам модуля

## 📊 Полный список методов

| Метод | Описание | Параметры |
|-------|----------|-----------|
| `findOrCreateContact` | Найти или создать контакт | properties |
| `updateContact` | Обновить контакт | contactId, data |
| `deleteContact` | **Удалить контакт** | contactId |
| `findOrCreateCompany` | Найти или создать компанию | properties |
| `updateCompany` | Обновить компанию | companyId, data |
| `deleteCompany` | **Удалить компанию** | companyId |
| `createRequisites` | Создать реквизиты | companyId, requisites |
