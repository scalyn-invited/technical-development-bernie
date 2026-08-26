// userService.js
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
  const response = await fetch(`https://jsonplaceholder.typicode.com/users/${userId}`);
  const user = await response.json(); // BUG 1: Missing await
  return user;
}

function updateUser(id, updates) {
  const user = users.find(u => u.id === id);
  if (!user) {
    return false; // User not found
  }
  Object.assign(user, updates); // BUG 2: Potential null dereference
  return user;
}

function deleteUser(userId) {
  const index = users.findIndex(u => u.id === userId);
  if(index === -1) {
    return false; // User not found
  }
  users.splice(index, 1); // BUG 3: What if userId doesn't exist?
}

const userService = { addUser, getUserById, fetchUserFromAPI, updateUser, deleteUser };
export default userService;

// Usage example:
// addUser({ name: 'Alice', email: 'alice@example.com' }); // BUG 4: Missing email
// getUserById(1);
// console.log(getUserById(1));

// fetchUserFromAPI(1).then(user => console.log(user));
// updateUser(999, { name: 'Bob' }); // Updating a user that doesn't exist
// deleteUser(1);