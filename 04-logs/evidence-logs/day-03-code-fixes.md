
# Evidence Log: Day 3 Fixed Code
**Date:** Tuesday, 26 August 2026  
**Exercise:** Broken Code Debug I — JavaScript  
**Code Status:** All bugs identified and fixed

---

## Fixed userService.js

```javascript
// userService.js — FIXED VERSION
const users = [];

function addUser(userData) {
  if (!userData.name || !userData.email) {
    return false;
  }
  
  users.push({
    id: users.length + 1,
    name: userData.name,
    email: userData.email,
    created: new Date()
  });
  
  return true;
}

function getUserById(id) {
  return users.find(u => u.id === id);
}

async function fetchUserFromAPI(userId) {
  const response = await fetch(`/api/users/${userId}`);
  const user = await response.json(); // ✅ FIX 1: Added await
  return user;
}

function updateUser(id, updates) {
  const user = users.find(u => u.id === id);
  if (!user) {
    return false; // ✅ FIX 2: Added null check
  }
  Object.assign(user, updates);
  return user;
}

function deleteUser(userId) {
  const index = users.findIndex(u => u.id === userId);
  if (index === -1) {
    return false; // ✅ FIX 3: Added guard against -1
  }
  users.splice(index, 1);
  return true;
}

const userService = { addUser, getUserById, fetchUserFromAPI, updateUser, deleteUser };
export default userService;

// ✅ FIXED USAGE EXAMPLE:
addUser({ name: 'Alice', email: 'alice@example.com' }); // ✅ FIX 4: Added email
getUserById(1);
console.log(getUserById(1));

// ✅ FIX 5: Wrapped in async context
(async () => {
  const user = await fetchUserFromAPI(1);
  console.log(user);
})();

updateUser(999, { name: 'Bob' }); // Now returns false gracefully
deleteUser(1); // Now returns false if user doesn't exist