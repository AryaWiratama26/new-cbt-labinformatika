import { seedTestData } from './fixtures/seed';

export default async function globalSetup() {
  seedTestData();
}
