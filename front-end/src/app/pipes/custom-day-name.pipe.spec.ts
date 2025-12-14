import { CustomDayNamePipe } from './custom-day-name.pipe';

describe('CustomDayNamePipe', () => {
  it('create an instance', () => {
    const pipe = new CustomDayNamePipe();
    expect(pipe).toBeTruthy();
  });
});
